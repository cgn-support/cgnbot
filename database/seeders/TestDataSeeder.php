<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\CrawledPage;
use App\Models\CrawlerSetting;
use App\Models\CrawlIssue;
use App\Models\CrawlRun;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        CrawlerSetting::firstOrCreate([], [
            'default_crawl_frequency_hours' => 24,
            'default_screenshot_frequency_hours' => 168,
            'default_max_depth' => 5,
            'default_crawl_limit' => 500,
            'default_concurrency' => 3,
            'default_slow_response_threshold_ms' => 3000,
            'default_thin_content_threshold' => 300,
            'default_visual_diff_threshold' => 15,
            'crawl_runs_to_keep' => 10,
            'resolved_issues_retention_days' => 90,
            'alert_on_severity' => ['critical'],
        ]);

        $summit = Client::firstOrCreate(
            ['slug' => 'summit-design-build'],
            [
                'name' => 'Summit Design Build',
                'domain' => 'https://summitdesignbuild.com',
                'is_active' => true,
                'last_crawled_at' => now()->subHours(6),
                'settings' => [
                    'crawl_frequency_hours' => 12,
                    'max_depth' => 5,
                    'crawl_limit' => 500,
                    'concurrency' => 3,
                    'excluded_patterns' => ['/wp-admin', '/wp-login.php'],
                    'monitored_urls' => ['/', '/services', '/portfolio', '/contact'],
                ],
                'slack_channel' => '#summit-alerts',
            ]
        );

        $coastal = Client::firstOrCreate(
            ['slug' => 'coastal-pools-spas'],
            [
                'name' => 'Coastal Pools & Spas',
                'domain' => 'https://coastalpoolsandspas.com',
                'is_active' => true,
                'settings' => [
                    'crawl_frequency_hours' => 24,
                    'max_depth' => 3,
                    'crawl_limit' => 200,
                    'concurrency' => 2,
                    'excluded_patterns' => ['/wp-admin'],
                    'monitored_urls' => ['/', '/pools', '/spas'],
                ],
                'slack_channel' => '#coastal-alerts',
            ]
        );

        Client::firstOrCreate(
            ['slug' => 'heritage-landscapes'],
            [
                'name' => 'Heritage Landscapes',
                'domain' => 'https://heritagelandscapes.com',
                'is_active' => false,
                'settings' => [],
                'notes' => 'Paused contract as of 2024-01.',
            ]
        );

        foreach ([$summit, $coastal] as $client) {
            $completedRun = CrawlRun::firstOrCreate(
                ['client_id' => $client->id, 'status' => 'completed'],
                [
                    'triggered_manually' => false,
                    'pages_crawled' => 45,
                    'pages_with_issues' => 8,
                    'critical_issues_found' => 2,
                    'warning_issues_found' => 4,
                    'info_issues_found' => 2,
                    'started_at' => now()->subHours(7),
                    'finished_at' => now()->subHours(6)->subMinutes(55),
                ]
            );

            CrawlRun::firstOrCreate(
                ['client_id' => $client->id, 'status' => 'failed'],
                [
                    'triggered_manually' => true,
                    'pages_crawled' => 0,
                    'pages_with_issues' => 0,
                    'critical_issues_found' => 0,
                    'warning_issues_found' => 0,
                    'info_issues_found' => 0,
                    'started_at' => now()->subDays(2),
                    'finished_at' => now()->subDays(2)->addSeconds(15),
                    'error_message' => 'Connection timeout after 30 seconds',
                ]
            );

            $baseUrl = $client->domain;
            $pages = ['/', '/services', '/portfolio', '/about', '/contact', '/blog', '/testimonials', '/faq', '/privacy-policy', '/sitemap'];
            foreach ($pages as $page) {
                CrawledPage::firstOrCreate(
                    ['crawl_run_id' => $completedRun->id, 'url' => $baseUrl.$page],
                    [
                        'client_id' => $client->id,
                        'status_code' => 200,
                        'canonical_is_self' => true,
                        'meta_title' => ucfirst(trim($page, '/')).' | '.$client->name,
                        'meta_title_length' => 40,
                        'meta_description' => 'Professional services from '.$client->name,
                        'meta_description_length' => 45,
                        'h1' => ucfirst(trim($page, '/') ?: 'Home'),
                        'h1_count' => 1,
                        'word_count' => rand(300, 1500),
                        'is_indexable' => true,
                        'in_sitemap' => true,
                        'has_schema_markup' => $page === '/',
                        'schema_types' => $page === '/' ? ['LocalBusiness'] : null,
                        'internal_links_count' => rand(10, 40),
                        'external_links_count' => rand(0, 5),
                        'broken_links_count' => 0,
                        'response_time_ms' => rand(150, 1200),
                        'page_hash' => md5($baseUrl.$page),
                        'first_seen_at' => now()->subDays(30),
                        'last_seen_at' => now()->subHours(6),
                    ]
                );
            }

            $issueData = [
                ['issue_type' => 'missing_description', 'severity' => 'critical', 'url' => $baseUrl.'/blog'],
                ['issue_type' => 'missing_h1', 'severity' => 'critical', 'url' => $baseUrl.'/faq'],
                ['issue_type' => 'thin_content', 'severity' => 'warning', 'url' => $baseUrl.'/privacy-policy'],
                ['issue_type' => 'slow_response', 'severity' => 'warning', 'url' => $baseUrl.'/portfolio'],
                ['issue_type' => 'missing_schema', 'severity' => 'warning', 'url' => $baseUrl.'/services'],
                ['issue_type' => 'duplicate_title', 'severity' => 'warning', 'url' => $baseUrl.'/about'],
                ['issue_type' => 'missing_canonical', 'severity' => 'info', 'url' => $baseUrl.'/testimonials'],
                ['issue_type' => 'noindex', 'severity' => 'info', 'url' => $baseUrl.'/sitemap'],
                ['issue_type' => 'broken_link', 'severity' => 'critical', 'url' => $baseUrl.'/old-page', 'resolved_at' => now()->subDays(3)],
                ['issue_type' => 'redirect_chain', 'severity' => 'warning', 'url' => $baseUrl.'/services/old', 'resolved_at' => now()->subDays(5)],
            ];

            foreach ($issueData as $issue) {
                CrawlIssue::firstOrCreate(
                    [
                        'client_id' => $client->id,
                        'crawl_run_id' => $completedRun->id,
                        'url' => $issue['url'],
                        'issue_type' => $issue['issue_type'],
                    ],
                    [
                        'severity' => $issue['severity'],
                        'context' => ['message' => 'Auto-detected during crawl run'],
                        'detected_at' => now()->subHours(6),
                        'resolved_at' => $issue['resolved_at'] ?? null,
                    ]
                );
            }
        }
    }
}
