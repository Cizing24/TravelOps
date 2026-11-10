@props(['value'])
@php
    $tone = match ($value) {
        'paid', 'fulfilled', 'active', 'unused', 'redeemed', 'succeeded', 'sent', 'resolved', 'processed' => 'success',
        'pending', 'pending_payment', 'requested', 'retryable_failed', 'requires_review', 'held', 'open', 'warning', 'redemption_paused' => 'warning',
        'failed', 'permanently_failed', 'revoked', 'rejected', 'high' => 'danger',
        'fulfilling', 'awaiting_callback', 'initiated', 'approved', 'submitted', 'queued' => 'info',
        default => 'neutral',
    };
@endphp
<span class="badge" data-tone="{{ $tone }}">{{ __('status.'.$value) }}</span>
