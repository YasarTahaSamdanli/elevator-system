<?php

namespace App\Providers;

use App\Services\InspectionImport\ImapMailFetcher;
use App\Services\InspectionImport\MailFetcherInterface;
use App\Services\InspectionImport\PdfTextExtractorInterface;
use App\Services\InspectionImport\SmalotPdfTextExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfTextExtractorInterface::class, SmalotPdfTextExtractor::class);
        $this->app->bind(MailFetcherInterface::class, ImapMailFetcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Global request budget for every /api route: keyed per user when
     * authenticated, per IP otherwise. The web dashboard fans out across
     * several list endpoints at once, so this budget is intentionally higher
     * than Laravel's default. Login keeps its own stricter per-email throttle
     * in AuthController on top of this.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(240)->by(
                $request->user()?->id ?? $request->ip()
            );
        });
    }
}
