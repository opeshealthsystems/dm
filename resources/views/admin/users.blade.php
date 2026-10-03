@extends('layouts.dashboard', ['area' => 'admin'])
@section('title', __('admin.users.title') . ' | ' . config('app.name'))

@section('content')
@include('admin._lib')
<div x-data="adminUsers()">
    <h1 class="mb-4 text-2xl font-medium">{{ __('admin.users.title') }}</h1>
    <form class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px]" @submit.prevent="reset()">
        <div><label for="q">{{ __('common.search') }}</label>
            <input id="q" type="search" x-model="filters.q" placeholder="{{ __('admin.users.search') }}" maxlength="100"></div>
        <div><label for="role">{{ __('admin.users.role') }}</label>
            <select id="role" x-model="filters.role" @change="reset()">
                <option value="">{{ __('admin.shared.all') }}</option>
                @foreach (['buyer', 'vendor', 'admin'] as $r)<option value="{{ $r }}">{{ __("admin.users.roles.$r") }}</option>@endforeach
            </select></div>
    </form>
    @include('admin._status')

    <div class="table-wrap hidden md:block" x-show="!loading" x-cloak>
        <table>
            <thead><tr><th>{{ __('admin.users.name') }}</th><th>{{ __('admin.users.role') }}</th><th>{{ __('admin.users.status') }}</th><th><span class="sr-only">{{ __('admin.shared.actions') }}</span></th></tr></thead>
            <tbody>
            <template x-for="u in items" :key="u.id">
                <tr>
                    <td><div class="font-medium" x-text="u.name"></div><div class="text-sm text-ink-3" x-text="u.email"></div></td>
                    <td x-text="t('admin.users.roles.' + u.role)"></td>
                    <td><span class="badge" :class="u.suspended_at ? 'badge-bad' : 'badge-ok'" x-text="u.suspended_at ? t('admin.users.suspended') : t('admin.users.active')"></span>
                        <span class="badge ms-1" :class="u.is_verified_vendor ? 'badge-accent' : ''" x-show="u.role === 'vendor'" x-text="u.is_verified_vendor ? t('admin.users.verified') : t('admin.users.unverified')"></span></td>
                    <td>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" class="btn btn-sm" x-show="u.role === 'vendor'" @click="u.is_verified_vendor ? act('admin/users/' + u.id + '/unverify') : act('admin/users/' + u.id + '/verify')" x-text="u.is_verified_vendor ? t('admin.users.unverify') : t('admin.users.verify')"></button>
                            <button type="button" class="btn btn-sm btn-danger" x-show="!u.suspended_at && u.role !== 'admin'" @click="suspend(u)">{{ __('admin.users.suspend') }}</button>
                            <button type="button" class="btn btn-sm" x-show="u.suspended_at" @click="act('admin/users/' + u.id + '/unsuspend')">{{ __('admin.users.unsuspend') }}</button>
                        </div></td>
                </tr>
            </template>
            </tbody>
        </table>
    </div>

    <div class="grid gap-3 md:hidden" x-show="!loading" x-cloak>
        <template x-for="u in items" :key="'m' + u.id">
            <article class="card">
                <div class="font-medium" x-text="u.name"></div><div class="text-sm text-ink-3" x-text="u.email"></div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="badge" x-text="t('admin.users.roles.' + u.role)"></span>
                    <span class="badge" :class="u.suspended_at ? 'badge-bad' : 'badge-ok'" x-text="u.suspended_at ? t('admin.users.suspended') : t('admin.users.active')"></span>
                    <span class="badge" :class="u.is_verified_vendor ? 'badge-accent' : ''" x-show="u.role === 'vendor'" x-text="u.is_verified_vendor ? t('admin.users.verified') : t('admin.users.unverified')"></span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm" x-show="u.role === 'vendor'" @click="u.is_verified_vendor ? act('admin/users/' + u.id + '/unverify') : act('admin/users/' + u.id + '/verify')" x-text="u.is_verified_vendor ? t('admin.users.unverify') : t('admin.users.verify')"></button>
                    <button type="button" class="btn btn-sm btn-danger" x-show="!u.suspended_at && u.role !== 'admin'" @click="suspend(u)">{{ __('admin.users.suspend') }}</button>
                    <button type="button" class="btn btn-sm" x-show="u.suspended_at" @click="act('admin/users/' + u.id + '/unsuspend')">{{ __('admin.users.unsuspend') }}</button>
                </div>
            </article>
        </template>
    </div>
    <p x-show="!loading && !error && items.length === 0" x-cloak class="card text-ink-2">{{ __('common.empty') }}</p>
    @include('admin._pager')
</div>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('adminUsers', () => ({
        ...adminList('admin/users', {q: '', role: ''}),
        async suspend(u) {
            const ok = await Alpine.store('dlg').ask({title: t('admin.users.confirm_suspend')});
            if (ok !== null) this.act('admin/users/' + u.id + '/suspend');
        },
    }));
});
</script>
@endsection
