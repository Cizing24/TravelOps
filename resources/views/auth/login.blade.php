<x-layout title="登入">
    <section class="card mx-auto max-w-md">
        <p class="kicker muted">TravelOps</p><h1 class="mt-2">登入</h1>
        <p class="muted mt-2">使用旅客、場館或營運帳號登入。</p>
        <form method="post" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf
            <div><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required></div>
            <div><label for="password">密碼</label><input id="password" type="password" name="password" autocomplete="current-password" required></div>
            <button type="submit" class="button w-full">登入</button>
        </form>
    </section>
</x-layout>
