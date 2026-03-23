<?php

use App\Analyzers\Checks\BrokenLinksCheck;
use App\Analyzers\Checks\CanonicalMismatchCheck;
use App\Analyzers\Checks\ContentChangedCheck;
use App\Analyzers\Checks\DuplicateTitleCheck;
use App\Analyzers\Checks\HomepageDownCheck;
use App\Analyzers\Checks\MissingH1Check;
use App\Analyzers\Checks\MissingMetaDescriptionCheck;
use App\Analyzers\Checks\MissingTitleCheck;
use App\Analyzers\Checks\MultipleH1Check;
use App\Analyzers\Checks\NewPagesDetectedCheck;
use App\Analyzers\Checks\NoindexOnMonitoredUrlCheck;
use App\Analyzers\Checks\PageDisappearedCheck;
use App\Analyzers\Checks\RedirectChainCheck;
use App\Analyzers\Checks\SiteWideNoindexCheck;
use App\Analyzers\Checks\SlowPageCheck;
use App\Analyzers\Checks\ThinContentCheck;
use App\Analyzers\Checks\TitleTooLongCheck;
use App\Analyzers\Checks\TitleTooShortCheck;
use App\Analyzers\Checks\VisualRegressionCheck;
use App\Models\Client;
use App\Models\CrawledPage;
use App\Models\CrawlRun;
use App\Models\PageScreenshot;

beforeEach(function () {
    $this->settings = [
        'monitored_urls' => ['/'],
        'slow_response_threshold_ms' => 3000,
        'thin_content_threshold' => 300,
        'visual_diff_threshold' => 15,
        'excluded_patterns' => [],
    ];

    $this->client = Client::factory()->create(['domain' => 'https://example.com']);
    $this->crawlRun = CrawlRun::factory()->create(['client_id' => $this->client->id]);
});

// --- HomepageDownCheck ---

it('HomepageDownCheck flags nothing when homepage returns 200', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/',
            'status_code' => 200,
        ]),
    ]);

    $issues = (new HomepageDownCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('HomepageDownCheck flags critical issue when homepage returns 500', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/',
            'status_code' => 500,
        ]),
    ]);

    $issues = (new HomepageDownCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('critical');
    expect($issues->first()['issue_type'])->toBe('HomepageDownCheck');
    expect($issues->first()['confidence'])->toBe(100);
});

it('HomepageDownCheck flags critical when homepage is missing from crawl', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'status_code' => 200,
        ]),
    ]);

    $issues = (new HomepageDownCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('critical');
    expect($issues->first()['issue_type'])->toBe('HomepageMissingFromCrawl');
    expect($issues->first()['confidence'])->toBe(80);
});

// --- BrokenLinksCheck ---

it('BrokenLinksCheck flags nothing when all pages return 200', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'status_code' => 200,
        ]),
    ]);

    $issues = (new BrokenLinksCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('BrokenLinksCheck flags warning for internal 404 page', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/missing',
            'status_code' => 404,
        ]),
    ]);

    $issues = (new BrokenLinksCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('warning');
    expect($issues->first()['issue_type'])->toBe('BrokenLinksCheck');
    expect($issues->first()['context']['is_internal'])->toBeTrue();
});

it('BrokenLinksCheck flags critical for internal 503 page', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/error',
            'status_code' => 503,
        ]),
    ]);

    $issues = (new BrokenLinksCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('critical');
    expect($issues->first()['issue_type'])->toBe('BrokenLinksCheck');
    expect($issues->first()['context']['is_internal'])->toBeTrue();
});

it('BrokenLinksCheck flags info for external 404 page', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://other-site.com/missing',
            'status_code' => 404,
        ]),
    ]);

    $issues = (new BrokenLinksCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('info');
    expect($issues->first()['context']['is_internal'])->toBeFalse();
});

it('BrokenLinksCheck flags warning for external 503 page', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://other-site.com/error',
            'status_code' => 503,
        ]),
    ]);

    $issues = (new BrokenLinksCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('warning');
    expect($issues->first()['context']['is_internal'])->toBeFalse();
});

// --- NoindexOnMonitoredUrlCheck ---

it('NoindexOnMonitoredUrlCheck flags nothing when monitored url is indexable', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/',
            'is_indexable' => true,
        ]),
    ]);

    $issues = (new NoindexOnMonitoredUrlCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('NoindexOnMonitoredUrlCheck flags critical when monitored url has noindex', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/',
            'is_indexable' => false,
        ]),
    ]);

    $issues = (new NoindexOnMonitoredUrlCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('critical');
    expect($issues->first()['issue_type'])->toBe('NoindexOnMonitoredUrlCheck');
    expect($issues->first()['confidence'])->toBe(95);
});

it('NoindexOnMonitoredUrlCheck resolves paths against client domain', function () {
    $settings = array_merge($this->settings, ['monitored_urls' => ['/services', '/contact']]);

    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/services',
            'is_indexable' => false,
        ]),
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/contact',
            'is_indexable' => true,
        ]),
    ]);

    $issues = (new NoindexOnMonitoredUrlCheck)->run($this->crawlRun, $this->client, $pages, collect(), $settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['url'])->toBe('https://example.com/services');
});

// --- SiteWideNoindexCheck ---

it('SiteWideNoindexCheck flags nothing when under 80% noindex', function () {
    $pages = collect();

    for ($i = 0; $i < 5; $i++) {
        $pages->push(CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
        ]));
    }

    for ($i = 0; $i < 5; $i++) {
        $pages->push(CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => false,
        ]));
    }

    $issues = (new SiteWideNoindexCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('SiteWideNoindexCheck flags critical when 90% of pages are noindex', function () {
    $pages = collect();

    $pages->push(CrawledPage::factory()->create([
        'crawl_run_id' => $this->crawlRun->id,
        'client_id' => $this->client->id,
        'is_indexable' => true,
    ]));

    for ($i = 0; $i < 9; $i++) {
        $pages->push(CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => false,
        ]));
    }

    $issues = (new SiteWideNoindexCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('critical');
    expect($issues->first()['issue_type'])->toBe('SiteWideNoindexCheck');
    expect($issues->first()['confidence'])->toBe(95);
});

it('SiteWideNoindexCheck flags nothing when no pages exist', function () {
    $issues = (new SiteWideNoindexCheck)->run($this->crawlRun, $this->client, collect(), collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- MissingTitleCheck ---

it('MissingTitleCheck flags nothing when indexable page has a title', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_title' => 'My Page Title',
        ]),
    ]);

    $issues = (new MissingTitleCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('MissingTitleCheck flags warning when indexable page has no title', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_title' => null,
        ]),
    ]);

    $issues = (new MissingTitleCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('warning');
    expect($issues->first()['issue_type'])->toBe('MissingTitleCheck');
    expect($issues->first()['confidence'])->toBe(70);
});

it('MissingTitleCheck ignores non-indexable pages', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => false,
            'meta_title' => null,
        ]),
    ]);

    $issues = (new MissingTitleCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- DuplicateTitleCheck ---

it('DuplicateTitleCheck flags nothing when all titles are unique', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_title' => 'Title One',
        ]),
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_title' => 'Title Two',
        ]),
    ]);

    $issues = (new DuplicateTitleCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('DuplicateTitleCheck flags all pages when 3 or more share the same title', function () {
    $sharedTitle = 'Duplicate Title Here';
    $pages = collect();

    for ($i = 0; $i < 3; $i++) {
        $pages->push(CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => "https://example.com/page-{$i}",
            'is_indexable' => true,
            'meta_title' => $sharedTitle,
        ]));
    }

    $issues = (new DuplicateTitleCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(3);
    expect($issues->first()['severity'])->toBe('warning');
    expect($issues->first()['issue_type'])->toBe('DuplicateTitleCheck');
    expect($issues->first()['confidence'])->toBe(85);
    expect($issues->first()['context']['title'])->toBe($sharedTitle);
    expect($issues->first()['context']['shared_with_count'])->toBe(3);
});

it('DuplicateTitleCheck does not flag when only 2 pages share a title', function () {
    $sharedTitle = 'Shared Title';
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_title' => $sharedTitle,
        ]),
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_title' => $sharedTitle,
        ]),
    ]);

    $issues = (new DuplicateTitleCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- TitleTooLongCheck ---

it('TitleTooLongCheck flags nothing when title is 55 characters', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_title' => str_repeat('A', 55),
            'meta_title_length' => 55,
        ]),
    ]);

    $issues = (new TitleTooLongCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('TitleTooLongCheck flags info when title is 65 characters', function () {
    $title = str_repeat('A', 65);
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_title' => $title,
            'meta_title_length' => 65,
        ]),
    ]);

    $issues = (new TitleTooLongCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('info');
    expect($issues->first()['issue_type'])->toBe('TitleTooLongCheck');
    expect($issues->first()['context']['length'])->toBe(65);
});

it('TitleTooLongCheck ignores non-indexable pages', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => false,
            'meta_title' => str_repeat('A', 65),
            'meta_title_length' => 65,
        ]),
    ]);

    $issues = (new TitleTooLongCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- TitleTooShortCheck ---

it('TitleTooShortCheck flags nothing when title is 35 characters', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_title' => str_repeat('A', 35),
            'meta_title_length' => 35,
        ]),
    ]);

    $issues = (new TitleTooShortCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('TitleTooShortCheck flags info when title is 20 characters', function () {
    $title = str_repeat('A', 20);
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_title' => $title,
            'meta_title_length' => 20,
        ]),
    ]);

    $issues = (new TitleTooShortCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('info');
    expect($issues->first()['issue_type'])->toBe('TitleTooShortCheck');
    expect($issues->first()['context']['length'])->toBe(20);
    expect($issues->first()['confidence'])->toBe(85);
});

it('TitleTooShortCheck does not flag pages with zero-length title', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_title' => null,
            'meta_title_length' => 0,
        ]),
    ]);

    $issues = (new TitleTooShortCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- MissingMetaDescriptionCheck ---

it('MissingMetaDescriptionCheck flags nothing when description is present', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_description' => 'A proper meta description for this page.',
        ]),
    ]);

    $issues = (new MissingMetaDescriptionCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('MissingMetaDescriptionCheck flags warning when description is missing', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'meta_description' => null,
        ]),
    ]);

    $issues = (new MissingMetaDescriptionCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('warning');
    expect($issues->first()['issue_type'])->toBe('MissingMetaDescriptionCheck');
    expect($issues->first()['confidence'])->toBe(70);
});

it('MissingMetaDescriptionCheck ignores non-indexable pages', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => false,
            'meta_description' => null,
        ]),
    ]);

    $issues = (new MissingMetaDescriptionCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- MissingH1Check ---

it('MissingH1Check flags nothing when h1 is present', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'h1' => 'Page Heading',
            'h1_count' => 1,
        ]),
    ]);

    $issues = (new MissingH1Check)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('MissingH1Check flags warning when h1_count is zero', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'h1' => null,
            'h1_count' => 0,
        ]),
    ]);

    $issues = (new MissingH1Check)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('warning');
    expect($issues->first()['issue_type'])->toBe('MissingH1Check');
    expect($issues->first()['confidence'])->toBe(60);
});

it('MissingH1Check ignores non-indexable pages', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => false,
            'h1' => null,
            'h1_count' => 0,
        ]),
    ]);

    $issues = (new MissingH1Check)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- MultipleH1Check ---

it('MultipleH1Check flags nothing when h1_count is 1', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'h1_count' => 1,
        ]),
    ]);

    $issues = (new MultipleH1Check)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('MultipleH1Check flags info when h1_count is 3', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'h1_count' => 3,
        ]),
    ]);

    $issues = (new MultipleH1Check)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('info');
    expect($issues->first()['issue_type'])->toBe('MultipleH1Check');
    expect($issues->first()['context']['h1_count'])->toBe(3);
    expect($issues->first()['confidence'])->toBe(60);
});

it('MultipleH1Check ignores non-indexable pages', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => false,
            'h1_count' => 5,
        ]),
    ]);

    $issues = (new MultipleH1Check)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- SlowPageCheck ---

it('SlowPageCheck flags nothing when page is fast', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'response_time_ms' => 500,
        ]),
    ]);

    $issues = (new SlowPageCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('SlowPageCheck flags warning for slow page with no previous data', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/slow',
            'response_time_ms' => 5000,
        ]),
    ]);

    $issues = (new SlowPageCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('warning');
    expect($issues->first()['issue_type'])->toBe('SlowPageCheck');
    expect($issues->first()['confidence'])->toBe(90);
    expect($issues->first()['context']['is_regression'])->toBeTrue();
});

it('SlowPageCheck flags info for page that was already slow in previous crawl', function () {
    $previousRun = CrawlRun::factory()->create(['client_id' => $this->client->id]);

    $previousPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $previousRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/slow',
            'response_time_ms' => 4000,
        ]),
    ]);

    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/slow',
            'response_time_ms' => 5000,
        ]),
    ]);

    $issues = (new SlowPageCheck)->run($this->crawlRun, $this->client, $currentPages, $previousPages, $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('info');
    expect($issues->first()['confidence'])->toBe(70);
    expect($issues->first()['context']['is_regression'])->toBeFalse();
});

// --- ThinContentCheck ---

it('ThinContentCheck flags nothing when page has 500 words', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'word_count' => 500,
        ]),
    ]);

    $issues = (new ThinContentCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('ThinContentCheck flags warning when page has 100 words', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/thin',
            'is_indexable' => true,
            'word_count' => 100,
        ]),
    ]);

    $issues = (new ThinContentCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('warning');
    expect($issues->first()['issue_type'])->toBe('ThinContentCheck');
    expect($issues->first()['confidence'])->toBe(50);
    expect($issues->first()['context']['word_count'])->toBe(100);
});

it('ThinContentCheck ignores pages with zero word count', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'word_count' => 0,
        ]),
    ]);

    $issues = (new ThinContentCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- RedirectChainCheck ---

it('RedirectChainCheck flags nothing when no redirects exist', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'redirect_count' => 0,
        ]),
    ]);

    $issues = (new RedirectChainCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('RedirectChainCheck flags warning when redirect_count is 3', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/old-page',
            'redirect_count' => 3,
            'redirect_url' => 'https://example.com/new-page',
        ]),
    ]);

    $issues = (new RedirectChainCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('warning');
    expect($issues->first()['issue_type'])->toBe('RedirectChainCheck');
    expect($issues->first()['confidence'])->toBe(95);
    expect($issues->first()['context']['redirect_count'])->toBe(3);
});

it('RedirectChainCheck does not flag single redirect', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'redirect_count' => 1,
        ]),
    ]);

    $issues = (new RedirectChainCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- CanonicalMismatchCheck ---

it('CanonicalMismatchCheck flags nothing when canonical is self-referencing', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'is_indexable' => true,
            'status_code' => 200,
            'canonical_url' => 'https://example.com/about',
            'canonical_is_self' => true,
        ]),
    ]);

    $issues = (new CanonicalMismatchCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('CanonicalMismatchCheck flags info when canonical does not match page url', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'is_indexable' => true,
            'status_code' => 200,
            'canonical_url' => 'https://example.com/about-us',
            'canonical_is_self' => false,
        ]),
    ]);

    $issues = (new CanonicalMismatchCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('info');
    expect($issues->first()['issue_type'])->toBe('CanonicalMismatchCheck');
    expect($issues->first()['context']['reason'])->toBe('mismatch');
    expect($issues->first()['confidence'])->toBe(90);
});

it('CanonicalMismatchCheck flags info when canonical is missing', function () {
    $pages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'is_indexable' => true,
            'status_code' => 200,
            'canonical_url' => null,
            'canonical_is_self' => false,
        ]),
    ]);

    $issues = (new CanonicalMismatchCheck)->run($this->crawlRun, $this->client, $pages, collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('info');
    expect($issues->first()['context']['reason'])->toBe('missing_canonical');
    expect($issues->first()['confidence'])->toBe(80);
});

// --- PageDisappearedCheck ---

it('PageDisappearedCheck flags nothing when same pages exist in both runs', function () {
    $previousRun = CrawlRun::factory()->create(['client_id' => $this->client->id]);

    $previousPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $previousRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'is_indexable' => true,
            'status_code' => 200,
        ]),
    ]);

    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'status_code' => 200,
        ]),
    ]);

    $issues = (new PageDisappearedCheck)->run($this->crawlRun, $this->client, $currentPages, $previousPages, $this->settings);

    expect($issues)->toBeEmpty();
});

it('PageDisappearedCheck flags warning when a page disappears', function () {
    $previousRun = CrawlRun::factory()->create(['client_id' => $this->client->id]);

    $previousPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $previousRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/services',
            'is_indexable' => true,
            'status_code' => 200,
        ]),
    ]);

    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'status_code' => 200,
        ]),
    ]);

    $issues = (new PageDisappearedCheck)->run($this->crawlRun, $this->client, $currentPages, $previousPages, $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('warning');
    expect($issues->first()['issue_type'])->toBe('PageDisappearedCheck');
    expect($issues->first()['confidence'])->toBe(80);
});

it('PageDisappearedCheck flags nothing when no previous pages exist', function () {
    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'status_code' => 200,
        ]),
    ]);

    $issues = (new PageDisappearedCheck)->run($this->crawlRun, $this->client, $currentPages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- NewPagesDetectedCheck ---

it('NewPagesDetectedCheck flags nothing when same pages exist in both runs', function () {
    $previousRun = CrawlRun::factory()->create(['client_id' => $this->client->id]);

    $previousPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $previousRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'status_code' => 200,
        ]),
    ]);

    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'status_code' => 200,
        ]),
    ]);

    $issues = (new NewPagesDetectedCheck)->run($this->crawlRun, $this->client, $currentPages, $previousPages, $this->settings);

    expect($issues)->toBeEmpty();
});

it('NewPagesDetectedCheck flags info for new pages', function () {
    $previousRun = CrawlRun::factory()->create(['client_id' => $this->client->id]);

    $previousPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $previousRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'status_code' => 200,
        ]),
    ]);

    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'status_code' => 200,
        ]),
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/new-page',
            'status_code' => 200,
        ]),
    ]);

    $issues = (new NewPagesDetectedCheck)->run($this->crawlRun, $this->client, $currentPages, $previousPages, $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('info');
    expect($issues->first()['issue_type'])->toBe('NewPagesDetectedCheck');
    expect($issues->first()['confidence'])->toBe(100);
});

it('NewPagesDetectedCheck flags nothing when no previous pages exist', function () {
    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'status_code' => 200,
        ]),
    ]);

    $issues = (new NewPagesDetectedCheck)->run($this->crawlRun, $this->client, $currentPages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- VisualRegressionCheck ---

it('VisualRegressionCheck flags nothing when no screenshots exist', function () {
    $previousRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'started_at' => now()->subDays(2),
    ]);

    $issues = (new VisualRegressionCheck)->run($this->crawlRun, $this->client, collect(), collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('VisualRegressionCheck flags warning for screenshot with high diff percentage', function () {
    $previousRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'started_at' => now()->subDay(),
        'finished_at' => now()->subDay()->addMinutes(5),
    ]);

    PageScreenshot::factory()->create([
        'client_id' => $this->client->id,
        'url' => 'https://example.com/',
        'diff_percentage' => 25.50,
        'captured_at' => now()->subHours(6),
    ]);

    $issues = (new VisualRegressionCheck)->run($this->crawlRun, $this->client, collect(), collect(), $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('warning');
    expect($issues->first()['issue_type'])->toBe('VisualRegressionCheck');
    expect($issues->first()['confidence'])->toBe(70);
});

it('VisualRegressionCheck ignores screenshots below threshold', function () {
    $previousRun = CrawlRun::factory()->create([
        'client_id' => $this->client->id,
        'started_at' => now()->subDay(),
        'finished_at' => now()->subDay()->addMinutes(5),
    ]);

    PageScreenshot::factory()->create([
        'client_id' => $this->client->id,
        'url' => 'https://example.com/',
        'diff_percentage' => 5.00,
        'captured_at' => now()->subHours(6),
    ]);

    $issues = (new VisualRegressionCheck)->run($this->crawlRun, $this->client, collect(), collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

// --- ContentChangedCheck ---

it('ContentChangedCheck flags nothing when content is unchanged', function () {
    $hash = hash('sha256', 'same content');

    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'page_hash' => $hash,
        ]),
    ]);

    $previousRun = CrawlRun::factory()->create(['client_id' => $this->client->id]);
    $previousPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $previousRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'page_hash' => $hash,
        ]),
    ]);

    $issues = (new ContentChangedCheck)->run($this->crawlRun, $this->client, $currentPages, $previousPages, $this->settings);

    expect($issues)->toBeEmpty();
});

it('ContentChangedCheck flags info issue when content has changed', function () {
    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'page_hash' => hash('sha256', 'new content'),
        ]),
    ]);

    $previousRun = CrawlRun::factory()->create(['client_id' => $this->client->id]);
    $previousPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $previousRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'page_hash' => hash('sha256', 'old content'),
        ]),
    ]);

    $issues = (new ContentChangedCheck)->run($this->crawlRun, $this->client, $currentPages, $previousPages, $this->settings);

    expect($issues)->toHaveCount(1);
    expect($issues->first()['severity'])->toBe('info');
    expect($issues->first()['issue_type'])->toBe('ContentChangedCheck');
    expect($issues->first()['confidence'])->toBe(90);
    expect($issues->first()['context']['previous_hash'])->toBe(hash('sha256', 'old content'));
    expect($issues->first()['context']['current_hash'])->toBe(hash('sha256', 'new content'));
});

it('ContentChangedCheck flags nothing when no previous pages exist', function () {
    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'page_hash' => hash('sha256', 'some content'),
        ]),
    ]);

    $issues = (new ContentChangedCheck)->run($this->crawlRun, $this->client, $currentPages, collect(), $this->settings);

    expect($issues)->toBeEmpty();
});

it('ContentChangedCheck flags nothing when current page_hash is null', function () {
    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'page_hash' => null,
        ]),
    ]);

    $previousRun = CrawlRun::factory()->create(['client_id' => $this->client->id]);
    $previousPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $previousRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'page_hash' => hash('sha256', 'old content'),
        ]),
    ]);

    $issues = (new ContentChangedCheck)->run($this->crawlRun, $this->client, $currentPages, $previousPages, $this->settings);

    expect($issues)->toBeEmpty();
});

it('ContentChangedCheck flags nothing when previous page_hash is null', function () {
    $currentPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $this->crawlRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'page_hash' => hash('sha256', 'new content'),
        ]),
    ]);

    $previousRun = CrawlRun::factory()->create(['client_id' => $this->client->id]);
    $previousPages = collect([
        CrawledPage::factory()->create([
            'crawl_run_id' => $previousRun->id,
            'client_id' => $this->client->id,
            'url' => 'https://example.com/about',
            'page_hash' => null,
        ]),
    ]);

    $issues = (new ContentChangedCheck)->run($this->crawlRun, $this->client, $currentPages, $previousPages, $this->settings);

    expect($issues)->toBeEmpty();
});
