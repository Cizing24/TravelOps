<x-layout title="場館核銷">
    <p class="kicker muted">場館工作台</p><h1 class="mt-2 mb-6">場館核銷</h1>
    <div class="grid gap-4 lg:grid-cols-[1fr_2fr]">
        <section class="card self-start">
            <h2>驗證票券</h2>
            @if($venues->isEmpty())
                <p class="muted mt-4">目前沒有授權場館，請聯絡管理員。</p>
            @else
                <form method="post" action="{{ route('merchant.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div><label for="venue_id">核銷場館</label><select id="venue_id" name="venue_id" required>
                        @foreach($venues as $venue)<option value="{{ $venue->id }}" @selected((int)old('venue_id') === $venue->id)>{{ $venue->name }}</option>@endforeach
                    </select></div>
                    <div><label for="code">核銷代碼</label><input id="code" name="code" type="text" minlength="48" maxlength="48" autocomplete="off" spellcheck="false" required></div>
                    <button type="submit" class="button w-full">確認核銷</button>
                </form>
            @endif
        </section>
        <section class="card">
            <h2>最近核銷</h2>
            <p class="muted mt-2">顯示你可操作的場館，時間為臺灣時間。</p>
            <ul class="data-list mt-3">@forelse($records as $record)
                <li><div class="row"><strong>{{ $record->venue?->name }}</strong><x-time :value="$record->redeemed_at"/></div><p class="muted break-all text-sm">{{ $record->trace_id }}</p></li>
            @empty <li class="muted">目前沒有核銷紀錄。</li> @endforelse</ul>
            <div class="mt-4">{{ $records->links() }}</div>
        </section>
    </div>
</x-layout>
