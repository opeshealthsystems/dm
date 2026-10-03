@extends('layouts.dashboard', ['area' => 'admin'])
@section('title', __('admin.audit.title') . ' | ' . config('app.name'))

@section('content')
@include('admin._lib')
<div x-data="adminList('admin/audit-logs', {action: ''})">
    <h1 class="mb-4 text-2xl font-medium">{{ __('admin.audit.title') }}</h1>
    <form class="mb-4 max-w-sm" @submit.prevent="reset()">
        <label for="a">{{ __('admin.audit.filter') }}</label>
        <input id="a" x-model="filters.action" maxlength="60" placeholder="user.*">
    </form>
    @include('admin._status')
    <div class="table-wrap" x-show="!loading" x-cloak>
        <table>
            <thead><tr><th>{{ __('admin.audit.when') }}</th><th>{{ __('admin.audit.admin') }}</th><th>{{ __('admin.audit.action') }}</th><th>{{ __('admin.audit.target') }}</th></tr></thead>
            <tbody>
            <template x-for="l in items" :key="l.id">
                <tr>
                    <td class="whitespace-nowrap text-sm text-ink-2" x-text="fmtDate(l.created_at)"></td>
                    <td dir="ltr" class="text-sm rtl:text-end" x-text="l.actor?.email"></td>
                    <td class="font-medium" x-text="l.action"></td>
                    <td class="text-sm text-ink-2" x-text="(l.target_type ?? '') + ' ' + (l.target_id ?? '')"></td>
                </tr>
            </template>
            </tbody>
        </table>
    </div>
    <p x-show="!loading && !error && items.length === 0" x-cloak class="card text-ink-2">{{ __('admin.audit.empty') }}</p>
    @include('admin._pager')
</div>
@endsection
