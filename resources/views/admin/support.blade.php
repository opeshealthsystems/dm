@extends('layouts.dashboard', ['area' => 'admin'])
@section('title', __('pages.support.title') . ' | ' . config('app.name'))

@section('content')
@include('admin._lib')
<div x-data="adminList('admin/support', {status: ''})">
    <h1 class="mb-4 text-2xl font-medium">{{ __('pages.support.title') }}</h1>
    <form class="mb-4 max-w-xs" @submit.prevent="reset()">
        <label for="s-status">{{ __('pages.support.filter') }}</label>
        <select id="s-status" x-model="filters.status" @change="reset()">
            <option value="">{{ __('pages.support.all') }}</option>
            <option value="new">{{ __('pages.support.new') }}</option>
            <option value="handled">{{ __('pages.support.handled') }}</option>
        </select>
    </form>
    @include('admin._status')
    <ul class="flex flex-col gap-3" x-show="!loading" x-cloak>
        <template x-for="r in items" :key="r.id">
            <li class="card">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-medium" x-text="r.subject"></div>
                        <div class="text-sm text-ink-2">
                            <span class="font-medium">{{ __('pages.support.from') }}</span>
                            <span x-text="r.name"></span>
                            <a :href="'mailto:' + r.email" dir="ltr" x-text="r.email"></a>
                        </div>
                        <div class="text-sm text-ink-3">
                            <span class="font-medium">{{ __('pages.support.received') }}</span>
                            <span x-text="fmtDate(r.created_at)"></span>
                        </div>
                    </div>
                    <span class="badge" :class="r.status === 'new' ? 'badge-warn' : 'badge-ok'"
                          x-text="r.status === 'new' ? @js(__('pages.support.new')) : @js(__('pages.support.handled'))"></span>
                </div>
                <p class="mt-2 whitespace-pre-line" x-text="r.message"></p>
                <button type="button" class="btn btn-sm mt-3" x-show="r.status === 'new'"
                        @click="act('admin/support/' + r.id + '/handle')">{{ __('pages.support.mark_handled') }}</button>
            </li>
        </template>
    </ul>
    <p x-show="!loading && !error && items.length === 0" x-cloak class="card text-ink-2">{{ __('pages.support.empty') }}</p>
    @include('admin._pager')
</div>
@endsection
