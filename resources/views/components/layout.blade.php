<!doctype html>
<html lang="zh-Hant-TW">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <title>{{ $title ?? '旅遊票券' }}｜TravelOps</title>
    <link rel="stylesheet" href="{{ asset('build/app.css') }}">
    @livewireStyles
</head>
<body>
    <a class="button skip" href="#content">跳至主要內容</a>
    <header class="site-head">
        <nav class="shell site-nav" aria-label="主選單">
            <a class="wordmark" href="{{ route('products.index') }}"><span class="mark" aria-hidden="true">↗</span>TravelOps</a>
            <div class="nav-links">
                <a href="{{ route('products.index') }}">票券目錄</a>
                @auth
                    @if(auth()->user()->role === 'customer')
                        <a href="{{ route('orders.index') }}">我的訂單</a>
                        <a href="{{ route('tickets.index') }}">我的票券</a>
                    @elseif(auth()->user()->role === 'merchant')
                        <a href="{{ route('merchant.index') }}">場館核銷</a>
                    @elseif(auth()->user()->staffsPos())
                        <a href="{{ route('pos.stores') }}">門市 POS</a>
                    @else
                        <a href="{{ url('/ops') }}">營運後台</a>
                    @endif
                    <form method="post" action="{{ route('logout') }}">@csrf <button type="submit" class="cursor-pointer underline">登出</button></form>
                @else
                    <a class="button secondary" href="{{ route('login') }}">登入</a>
                @endauth
            </div>
        </nav>
    </header>
    <main id="content" class="shell" tabindex="-1">
        @if(session('notice')) <p class="notice mb-6" role="status">{{ session('notice') }}</p> @endif
        @if($errors->any())
            <div class="errors mb-6" role="alert">
                <p class="font-bold">請檢查輸入內容</p>
                <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        {{ $slot }}
    </main>
    <footer class="site-foot"><div class="shell muted">TravelOps · 電子票券與場館履約展示</div></footer>
    @livewireScripts
</body>
</html>
