<x-layout title="無法完成操作">
    <section class="card">
        <h1>無法完成操作</h1>
        <p class="errors mt-4" role="alert">{{ $message }}</p>
        <div class="mt-4">
            @if(auth()->user()?->role === 'merchant')
                <a class="button" href="{{ route('merchant.index') }}">回到場館核銷</a>
            @elseif(auth()->user()?->staffsPos())
                <a class="button" href="{{ route('pos.stores') }}">回到門市 POS</a>
            @elseif(auth()->user()?->operates())
                <a class="button" href="{{ url('/ops') }}">回到營運後台</a>
            @elseif(auth()->check())
                <a class="button" href="{{ route('orders.index') }}">查看我的訂單</a>
                <a class="button secondary" href="{{ route('products.index') }}">查看票券目錄</a>
            @else
                <a class="button" href="{{ route('products.index') }}">回到票券目錄</a>
            @endif
        </div>
    </section>
</x-layout>
