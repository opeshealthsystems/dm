<?php

namespace App\Modules\Payments\Support;

/**
 * BIP32 public (non-hardened) child derivation + P2PKH / native SegWit (BIP84) addresses.
 * Ported verbatim from the legacy BitcoinHelper (sealed payment file): do not change the
 * derivation path (zpub => m/0/index, bech32 bc1q...) or the maths. Public keys only; no private key ever touches this app.
 */
final class BitcoinHelper {
    private static $p = '115792089237316195423570985008687907853269984665640564039457584007908834671663';
    private static $n = '115792089237316195423570985008687907852837564279074904382605163141518161494337';
    private static $G = [
        'x' => '55066263022277343669578718895168534326250603453777594175500187360389116729240',
        'y' => '32670510020758816978083085130507043184471273380659243275938904335757337482424'
    ];

    public static function deriveAddress($xpub, $index, $change = 0) {
        if (!extension_loaded('bcmath')) {
            throw new \Exception("BCMath extension is required for Bitcoin derivation.");
        }

        // 1. Detect Version and Normalize to common XPUB hex structure
        // zpub (0x04b24746) = P2WPKH (Native SegWit)
        // ypub (0x049d7cb2) = P2WPKH-in-P2SH (Wrapped SegWit) - Add support if needed
        $isSegwit = (substr($xpub, 0, 4) === 'zpub');
        
        // 2. Validate XPUB/ZPUB (Checksum & Length)
        if (!self::validateXpub($xpub)) {
             throw new \Exception("Invalid Master Key: Checksum failed or incorrect format.");
        }

        // 3. Decode Base58
        $decoded = self::base58Decode($xpub);
        if (strlen($decoded) !== 164) {
             throw new \Exception("Invalid Master Key length.");
        }
        
        // [4:version][1:depth][4:parent][4:index][32:chain][33:key]
        $chainCode = substr($decoded, 26, 64);
        $key = substr($decoded, 90, 66);

        // 4. Derive External/Internal Chain (m/change)
        $m_level = self::deriveChild($key, $chainCode, $change);
        
        // 5. Derive Child Address (m/change/index)
        $child = self::deriveChild($m_level['key'], $m_level['chain'], $index);
        
        // 6. Convert Public Key to Address
        if ($isSegwit) {
            return self::publicKeyToSegwitAddress($child['key']);
        }
        return self::publicKeyToAddress($child['key']);
    }

    /**
     * Validate an XPUB string using Base58 checksum verification
     */
    public static function validateXpub($xpub) {
        try {
            $decoded = self::base58Decode($xpub);
            if (strlen($decoded) < 10) return false;

            $data = hex2bin(substr($decoded, 0, -8));
            $checksum = substr($decoded, -8);
            
            $expected = bin2hex(substr(hash('sha256', hash('sha256', $data, true), true), 0, 4));
            
            return hash_equals($expected, $checksum);
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function deriveChild($parentKey, $parentChain, $index) {
        // Data = ParentKey + Index (Big Endian 4 bytes)
        $data = hex2bin($parentKey . sprintf('%08x', $index));
        $hash = hash_hmac('sha512', $data, hex2bin($parentChain), true);
        
        $iL = bin2hex(substr($hash, 0, 32));
        $childChain = bin2hex(substr($hash, 32));
        
        // Child Key = iL*G + ParentPubKey
        $p1 = self::eccMul($iL, self::$G);
        $p2 = self::parsePublicKey($parentKey);
        $childPub = self::eccAdd($p1, $p2);
        
        $cx = str_pad(self::decToHex($childPub['x']), 64, '0', STR_PAD_LEFT);
        $prefix = (bcmod($childPub['y'], '2') == '0') ? '02' : '03';
        
        return [
            'key' => $prefix . $cx,
            'chain' => $childChain
        ];
    }

    private static function publicKeyToAddress($pubHex) {
        $sha = hash('sha256', hex2bin($pubHex), true);
        $rip = hash('ripemd160', $sha, true);
        
        $version = "\x00"; // Bitcoin Mainnet
        $payload = $version . $rip;
        
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
        return self::base58Encode($payload . $checksum);
    }

    // --- Cryptographic Primitives ---

    private static function eccAdd($p1, $p2) {
        if (!$p1) return $p2;
        if (!$p2) return $p1;
        
        if ($p1['x'] == $p2['x'] && $p1['y'] == $p2['y']) {
            $m = self::bc_mod(bcmul(bcmul('3', bcpow($p1['x'], '2')), self::bc_inverse(bcmul('2', $p1['y']), self::$p)), self::$p);
        } else {
            $m = self::bc_mod(bcmul(bcsub($p2['y'], $p1['y']), self::bc_inverse(bcsub($p2['x'], $p1['x']), self::$p)), self::$p);
        }
        
        $x3 = self::bc_mod(bcsub(bcsub(bcpow($m, '2'), $p1['x']), $p2['x']), self::$p);
        $y3 = self::bc_mod(bcsub(bcmul($m, bcsub($p1['x'], $x3)), $p1['y']), self::$p);
        
        return ['x' => $x3, 'y' => $y3];
    }

    private static function eccMul($kHex, $P) {
        $R = null;
        $k = self::hexToDec($kHex);
        $bin = self::decToBin($k);
        for ($i = 0; $i < strlen($bin); $i++) {
            $R = self::eccAdd($R, $R);
            if ($bin[$i] == '1') $R = self::eccAdd($R, $P);
        }
        return $R;
    }

    private static function parsePublicKey($hex) {
        $prefix = substr($hex, 0, 2);
        $x = self::hexToDec(substr($hex, 2));
        
        // y^2 = x^3 + 7
        // Use bcpowmod for efficiency to avoid calculating huge x^3
        $x3 = bcpowmod($x, '3', self::$p);
        $y2 = self::bc_mod(bcadd($x3, '7'), self::$p);
        $y = bcpowmod($y2, bcdiv(bcadd(self::$p, '1'), '4', 0), self::$p);
        
        $expectedYParity = ($prefix == '03') ? '1' : '0';
        if (bcmod($y, '2') != $expectedYParity) {
            $y = bcsub(self::$p, $y);
        }
        
        return ['x' => $x, 'y' => $y];
    }

    private static function bc_mod($n, $m) {
        $rem = bcmod($n, $m);
        if (bccomp($rem, '0') < 0) $rem = bcadd($rem, $m);
        return $rem;
    }

    private static function bc_inverse($a, $m) {
        // Same result as a^(m-2) mod m (m is prime); GMP is only a speed-up when available.
        if (function_exists('gmp_invert')) {
            $inv = gmp_invert(gmp_mod(gmp_init($a), gmp_init($m)), gmp_init($m));
            if ($inv !== false) {
                return gmp_strval($inv);
            }
        }
        return bcpowmod($a, bcsub($m, '2'), $m);
    }

    // --- Encoding / Conversion ---

    private static function base58Encode($data) {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $num = '0';
        for ($i = 0; $i < strlen($data); $i++) {
            $num = bcadd(bcmul($num, '256'), ord($data[$i]));
        }
        
        $res = '';
        while (bccomp($num, '0') > 0) {
            $res = $alphabet[bcmod($num, 58)] . $res;
            $num = bcdiv($num, 58, 0);
        }
        
        for ($i = 0; $i < strlen($data) && $data[$i] == "\x00"; $i++) {
            $res = '1' . $res;
        }
        
        return $res;
    }

    private static function base58Decode($base58) {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $num = '0';
        for ($i = 0; $i < strlen($base58); $i++) {
            $num = bcadd(bcmul($num, '58'), strpos($alphabet, $base58[$i]));
        }
        
        $hex = '';
        while (bccomp($num, '0') > 0) {
            $hex = dechex(bcmod($num, 16)) . $hex;
            $num = bcdiv($num, 16, 0);
        }
        
        if (strlen($hex) % 2 != 0) $hex = '0' . $hex;
        
        $zeros = 0;
        while ($zeros < strlen($base58) && $base58[$zeros] == '1') {
            $zeros++;
        }
        
        return str_repeat('00', $zeros) . $hex;
    }

    private static function hexToDec($hex) {
        $dec = '0';
        for ($i = 0; $i < strlen($hex); $i++) {
            $dec = bcadd(bcmul($dec, '16'), hexdec($hex[$i]));
        }
        return $dec;
    }

    private static function decToHex($dec) {
        $hex = '';
        while (bccomp($dec, '0') > 0) {
            $hex = dechex(bcmod($dec, 16)) . $hex;
            $dec = bcdiv($dec, 16, 0);
        }
        return $hex;
    }

    private static function decToBin($dec) {
        $bin = '';
        while (bccomp($dec, '0') > 0) {
            $bin = bcmod($dec, '2') . $bin;
            $dec = bcdiv($dec, '2', 0);
        }
        return $bin;
    }

    private static function publicKeyToSegwitAddress($pubHex) {
        $sha = hash('sha256', hex2bin($pubHex), true);
        $rip = hash('ripemd160', $sha, true);
        
        // Witness Version 0, Program is RIPEMD160(SHA256(PubKey))
        $witnessVersion = 0;
        // Convert pubkey hash to bits
        $witnessProgram = array_values(unpack('C*', $rip));
        
        return self::bech32Encode('bc', $witnessVersion, $witnessProgram);
    }

    /**
     * Native Bech32 Encoder (BIP173)
     */
    private static function bech32Encode($hrp, $witnessVersion, $witnessProgram) {
        $data = array_merge([$witnessVersion], self::bech32ConvertBits($witnessProgram, 8, 5, true));
        $checksum = self::bech32CreateChecksum($hrp, $data);
        $combined = array_merge($data, $checksum);
        
        $alphabet = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
        $res = $hrp . '1';
        foreach ($combined as $v) {
            $res .= $alphabet[$v];
        }
        return $res;
    }

    private static function bech32ConvertBits(array $data, $fromBits, $toBits, $pad = true) {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;
        foreach ($data as $value) {
            $acc = ($acc << $fromBits) | $value;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = ($acc >> $bits) & $maxv;
            }
        }
        if ($pad) {
            if ($bits > 0) {
                $ret[] = ($acc << ($toBits - $bits)) & $maxv;
            }
        } elseif ($bits >= $fromBits) {
            return null;
        }
        return $ret;
    }

    private static function bech32PolyMod(array $values) {
        $generator = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
        $chk = 1;
        foreach ($values as $v) {
            $top = $chk >> 25;
            $chk = (($chk & 0x1ffffff) << 5) ^ $v;
            for ($i = 0; $i < 5; $i++) {
                if (($top >> $i) & 1) {
                    $chk ^= $generator[$i];
                }
            }
        }
        return $chk;
    }

    private static function bech32HrpExpand($hrp) {
        $ret = [];
        for ($i = 0; $i < strlen($hrp); $i++) {
            $ret[] = ord($hrp[$i]) >> 5;
        }
        $ret[] = 0;
        for ($i = 0; $i < strlen($hrp); $i++) {
            $ret[] = ord($hrp[$i]) & 31;
        }
        return $ret;
    }

    private static function bech32CreateChecksum($hrp, array $data) {
        $values = array_merge(self::bech32HrpExpand($hrp), $data, [0, 0, 0, 0, 0, 0]);
        $mod = self::bech32PolyMod($values) ^ 1;
        $ret = [];
        for ($i = 0; $i < 6; $i++) {
            $ret[] = ($mod >> (5 * (5 - $i))) & 31;
        }
        return $ret;
    }
}
