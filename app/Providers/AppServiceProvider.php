<?php

namespace App\Providers;

use App\Services\MaidManagement\DataExtractor\MedicalExtractor;
use App\Services\MaidManagement\DataExtractor\PersonalInformationExtractor;
use App\Services\MaidManagement\DataExtractor\SectionExtractor;
use App\Services\MaidManagement\DataExtractor\SkillsAssessmentExtractor;
use App\Services\MaidManagement\DataExtractor\EmploymentExtractor;
use App\Services\MaidManagement\FileParser\DocxParser;
use App\Services\MaidManagement\FileParser\PdfParser;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register file parsers
        $this->app->singleton(DocxParser::class);
        $this->app->singleton(PdfParser::class);
        
        // Register data extractors
        $this->app->singleton(PersonalInformationExtractor::class);
        $this->app->singleton(MedicalExtractor::class);
        $this->app->singleton(SectionExtractor::class);
        $this->app->singleton(SkillsAssessmentExtractor::class, function ($app) {
            return new SkillsAssessmentExtractor('');
        });
        $this->app->singleton(EmploymentExtractor::class);
    }
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
