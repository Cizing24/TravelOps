# ADR 001 · 本地技術棧

採 PHP 8.4、Laravel 13、Filament 5、Livewire 4 及 Tailwind CSS 4。旅客與場館使用 Laravel session。Browser JSON 入口與頁面共用 session、CSRF 與授權規則。單一受簽章保護的 mock webhook 不需要 Sanctum。MySQL 8.4/InnoDB 保存交易，Redis 負責 Queue、快取與速率限制。Docker 使用 PHP 開發 Web server，僅綁 localhost 作展示。

相容性依 Laravel 13 release notes 與 Filament 5 的安裝要求核對，最終版本由 Composer lockfile 固定。Windows 原生驗收採 PHP ZIP、MySQL ZIP 與 Redis 7.4.7 Windows 移植版，Compose 使用 Linux images。

- [Laravel 13 release notes](https://laravel.com/framework/docs/13.x/releases)
- [Filament 5 安裝與相容性](https://filamentphp.com/docs/5.x/introduction/installation)
- [Windows Redis 測試版來源](https://github.com/redis-windows/redis-windows/releases/tag/7.4.7)
