@extends('layouts.store')

@section('title', __('pages.contact.title') . ' | ' . config('app.name'))
@section('description', __('pages.contact.meta'))

@section('content')
<div class="page-copy">
    @include('partials.breadcrumbs', ['crumbs' => [[__('pages.contact.title'), route('pages.contact')]]])
    <h1>{{ __('pages.contact.title') }}</h1>
    <p class="lead">{{ __('pages.contact.lead') }}</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,40rem)_minmax(0,1fr)]" x-data="contactForm()">
    <section class="card relative" aria-live="polite">
        <div x-show="sent" x-cloak role="status">
            <h2 class="mb-1 text-xl font-medium">{{ __('pages.contact.sent_title') }}</h2>
            <p class="text-ink-2">{{ __('pages.contact.sent_body') }}</p>
        </div>

        <form x-show="!sent" class="flex flex-col gap-3" novalidate @submit.prevent="submit()">
            <p x-show="error" x-cloak class="text-bad" role="alert" x-text="error"></p>
            <div>
                <label for="c-name">{{ __('pages.contact.name') }}</label>
                <input id="c-name" x-model="form.name" required maxlength="100" autocomplete="name">
                <p class="mt-1 text-sm text-bad" x-show="errors.name" x-cloak x-text="errors.name"></p>
            </div>
            <div>
                <label for="c-email">{{ __('pages.contact.email') }}</label>
                <input id="c-email" type="email" x-model="form.email" required maxlength="190" autocomplete="email" dir="ltr">
                <p class="mt-1 text-sm text-bad" x-show="errors.email" x-cloak x-text="errors.email"></p>
            </div>
            <div>
                <label for="c-subject">{{ __('pages.contact.subject') }}</label>
                <input id="c-subject" x-model="form.subject" required maxlength="150">
                <p class="mt-1 text-sm text-bad" x-show="errors.subject" x-cloak x-text="errors.subject"></p>
            </div>
            <div>
                <label for="c-message">{{ __('pages.contact.message') }}</label>
                <textarea id="c-message" x-model="form.message" required minlength="10" maxlength="5000" rows="6" aria-describedby="c-message-hint"></textarea>
                <p id="c-message-hint" class="mt-1 text-sm text-ink-3">{{ __('pages.contact.message_hint') }}</p>
                <p class="mt-1 text-sm text-bad" x-show="errors.message" x-cloak x-text="errors.message"></p>
            </div>
            {{-- Honeypot: invisible to people, tempting to bots. --}}
            <div class="absolute start-0 top-0 h-px w-px overflow-hidden opacity-0" aria-hidden="true">
                <label for="c-website">{{ __('pages.contact.honeypot') }}</label>
                <input id="c-website" name="website" x-model="form.website" tabindex="-1" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-primary" :disabled="busy">
                <span x-show="!busy">{{ __('pages.contact.send') }}</span>
                <span x-show="busy" x-cloak>{{ __('pages.contact.sending') }}</span>
            </button>
        </form>
    </section>

    <aside class="card self-start">
        <h2 class="mb-1 text-lg font-medium">{{ __('pages.contact.side_title') }}</h2>
        <p class="mb-3 max-w-prose text-ink-2">{{ __('pages.contact.side_body') }}</p>
        <a class="btn btn-sm" href="{{ route('pages.faq') }}">{{ __('pages.contact.side_cta') }}</a>
    </aside>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('contactForm', () => ({
        form: {name: '', email: '', subject: '', message: '', website: ''},
        errors: {}, error: '', busy: false, sent: false,
        async submit() {
            if (this.busy) return;
            this.busy = true; this.error = ''; this.errors = {};
            try {
                await api('support', {method: 'POST', body: this.form});
                this.sent = true;
            } catch (e) {
                if (e.status === 429) this.error = @js(__('pages.contact.too_many'));
                else if (e.status === 422) { Object.entries(e.errors).forEach(([k, v]) => { this.errors[k] = v[0]; }); }
                else this.error = errText(e);
            } finally { this.busy = false; }
        },
    }));
});
</script>
@endsection
