<?php

namespace App\Modules\Admin\Console;

use App\Models\User;
use App\Modules\Admin\Actions\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** The only way to create an admin account. */
class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {email : E-mail address of the new admin} {--name= : Display name}';

    protected $description = 'Create an administrator account (prompts for a strong password)';

    public function handle(AuditLogger $audit): int
    {
        $email = (string) $this->argument('email');
        $v = Validator::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:190', 'unique:users,email']]);
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (min 12 chars, mixed case, numbers, symbols)');
        $confirm = (string) $this->secret('Confirm password');
        if ($password !== $confirm) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $strength = Validator::make(['password' => $password], ['password' => [
            'required', Password::min(12)->mixedCase()->numbers()->symbols(),
        ]]);
        if ($strength->fails()) {
            foreach ($strength->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($email, $password, $audit) {
            $user = new User(['name' => $this->option('name') ?: 'Administrator', 'email' => $email, 'password' => $password]);
            $user->forceFill(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()])->save();
            $audit->record('admin.created', 'user', $user->id, null, ['email' => $email, 'role' => User::ROLE_ADMIN], null);

            return $user;
        });

        $this->info("Admin #{$user->id} created for {$email}.");

        return self::SUCCESS;
    }
}
