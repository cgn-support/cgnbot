# CGN Watchdog — Product Requirements Document

## Document Purpose

This PRD is a complete technical specification for building CGN Watchdog from scratch. It contains all architectural decisions, data schema, business logic, component specifications, and build instructions. No prior context is required to implement this project from this document alone.

---

## 1. Project Overview

### What It Is

CGN Watchdog is an internal Laravel application that continuously crawls the websites of Contractor Growth Network's 50 client sites, records key SEO and technical health signals on each crawl, detects issues and regressions, and alerts via Slack when critical problems are found.

It is analogous to a lightweight, self-hosted version of Screaming Frog combined with a monitoring layer — running automatically, comparing each crawl against the previous one, and surfacing actionable issues without manual intervention.

### Who Uses It

Internal use only. A single administrator (the SEO lead) accesses the Filament admin panel to monitor client health, review issues, and manage settings. There are no client-facing views, no multi-user roles, and no public-facing pages.

### Core Goals

- Crawl all 50 client websites on a configurable schedule (default: daily)
- Record SEO signals per page: title, meta description, H1, canonical, indexability, schema, link counts, word count, response time, page hash
- Compare each crawl against the previous run to detect regressions
- Run a library of named checks against crawl data and write structured issues
- Alert to Slack immediately when critical issues are found
- Take weekly screenshots of key pages per client using the SiteShot API
- Provide a Filament v5 admin UI to browse clients, crawl runs, issues, and screenshots
- Retain the last 10 crawl runs per client in the hot table; archive older runs automatically
- Prune resolved issues older than 90 days

### What It Does Not Do

- It does not check Google Search Console data
- It does not track keyword rankings (that stays in Keyword.com)
- It does not measure Core Web Vitals (PageSpeed Insights API is a future addition)
- It does not render JavaScript (all client sites are WordPress; standard HTTP crawling is sufficient)
- It does not have client-facing login or multi-tenant views

---

## 2. Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 |
| Admin UI | Filament v5 (Livewire v4, Tailwind v4) |
| Crawl engine | spatie/crawler (v9+) |
| HTML parsing | Symfony DomCrawler (already in Laravel ecosystem) |
| Queue management | Laravel Horizon with Redis |
| Database | MySQL 8 |
| Screenshots | SiteShot API |
| Alerts | Slack Incoming Webhooks (Block Kit format) |
| Storage | Local disk (configurable to S3/DO Spaces via env) |
| Server | DigitalOcean droplet managed via Ploi |

### Key Composer Packages

```
spatie/crawler
filament/filament:"^5.0"
laravel/horizon
symfony/dom-crawler (pulled in transitively by Laravel)
```

### Key npm Packages

Filament v5 requires Tailwind CSS v4. Follow the Filament v5 installation guide for the correct `tailwind.config.js` and CSS setup.

---

## 3. Project Structure

```
app/
  Analyzers/
    IssueAnalyzer.php            -- coordinates all checks
    Checks/
      CrawlCheck.php             -- interface + BuildsIssues trait
      HomepageDownCheck.php
      BrokenLinksCheck.php
      NoindexOnMonitoredUrlCheck.php
      SiteWideNoindexCheck.php
      MissingTitleCheck.php
      DuplicateTitleCheck.php
      TitleTooLongCheck.php
      TitleTooShortCheck.php
      MissingMetaDescriptionCheck.php
      MissingH1Check.php
      MultipleH1Check.php
      ThinContentCheck.php
      SlowPageCheck.php
      RedirectChainCheck.php
      CanonicalMismatchCheck.php
      PageDisappearedCheck.php
      NewPagesDetectedCheck.php

  Crawlers/
    ClientSettings.php           -- merges per-client settings over global defaults
    ClientCrawlProfile.php       -- controls which URLs spatie/crawler follows
    ClientCrawlObserver.php      -- extracts SEO signals per page, writes crawled_pages

  Filament/
    Resources/
      ClientResource.php
      ClientResource/Pages/
        ListClients.php
        CreateClient.php
        EditClient.php
        ViewClient.php
      CrawlRunResource.php
      CrawlRunResource/Pages/
        ListCrawlRuns.php
      CrawlIssueResource.php
      CrawlIssueResource/Pages/
        ListCrawlIssues.php
      PageScreenshotResource.php
      PageScreenshotResource/Pages/
        ListPageScreenshots.php
    Pages/
      CrawlerSettingsPage.php    -- single-record global settings form

  Jobs/
    CrawlClientJob.php
    AnalyzeCrawlRunJob.php
    AlertCriticalIssuesJob.php
    ScreenshotClientJob.php
    PruneOldCrawlRunsJob.php
    WeeklySummaryJob.php

  Models/
    CrawlerSetting.php
    Client.php
    CrawlRun.php
    CrawledPage.php
    CrawlIssue.php
    PageScreenshot.php

  Console/
    Commands/
      DispatchPendingCrawls.php
      DispatchPendingScreenshots.php

config/
  horizon.php
  services.php                   -- add siteshot.key entry

database/
  migrations/                    -- 7 migrations, see Section 4

routes/
  console.php                    -- schedule entries
```

---

## 4. Database Schema

Run migrations in the numbered order below. All tables use `id` as unsigned big integer primary key and `created_at`/`updated_at` timestamps unless noted.

### 4.1 crawler_settings

Single-row global configuration table. The migration seeds the row on creation so it always exists. Never create a second row.

```sql
id
default_crawl_frequency_hours        SMALLINT UNSIGNED  DEFAULT 24
default_screenshot_frequency_hours   SMALLINT UNSIGNED  DEFAULT 168
default_max_depth                    TINYINT UNSIGNED   DEFAULT 5
default_crawl_limit                  SMALLINT UNSIGNED  DEFAULT 500
default_concurrency                  TINYINT UNSIGNED   DEFAULT 3
default_slow_response_threshold_ms   SMALLINT UNSIGNED  DEFAULT 3000
default_thin_content_threshold       SMALLINT UNSIGNED  DEFAULT 300
crawl_runs_to_keep                   TINYINT UNSIGNED   DEFAULT 10
resolved_issues_retention_days       SMALLINT UNSIGNED  DEFAULT 90
slack_webhook_url                    VARCHAR nullable
slack_default_channel                VARCHAR nullable
alert_on_severity                    JSON               DEFAULT '["critical"]'
created_at, updated_at
```

### 4.2 clients

```sql
id
name                   VARCHAR
slug                   VARCHAR UNIQUE       -- URL-safe, e.g. "acme-remodeling"
domain                 VARCHAR              -- "https://example.com", no trailing slash
is_active              BOOLEAN DEFAULT true INDEX
last_crawled_at        TIMESTAMP nullable   INDEX
last_screenshot_at     TIMESTAMP nullable
settings               JSON nullable        -- per-client overrides only, see Section 5
slack_channel          VARCHAR nullable     -- overrides global channel if set
notes                  TEXT nullable
created_at, updated_at
```

The `settings` JSON column holds only the keys that differ from global defaults. At runtime, merge client settings over global defaults using `ClientSettings::for($client)`. Never store the full merged result.

Valid keys in `settings`:
```json
{
  "crawl_frequency_hours": 48,
  "screenshot_frequency_hours": 168,
  "max_depth": 5,
  "crawl_limit": 500,
  "concurrency": 3,
  "slow_response_threshold_ms": 3000,
  "thin_content_threshold": 300,
  "alert_on_severity": ["critical"],
  "monitored_urls": ["/", "/contact", "/services"],
  "excluded_patterns": ["/wp-admin", "?s=", "/feed"]
}
```

### 4.3 crawl_runs

```sql
id
client_id              FK -> clients.id CASCADE DELETE
status                 ENUM('pending','running','completed','failed') DEFAULT 'pending' INDEX
triggered_manually     BOOLEAN DEFAULT false
pages_crawled          SMALLINT UNSIGNED DEFAULT 0
pages_with_issues      SMALLINT UNSIGNED DEFAULT 0
critical_issues_found  SMALLINT UNSIGNED DEFAULT 0
warning_issues_found   SMALLINT UNSIGNED DEFAULT 0
info_issues_found      SMALLINT UNSIGNED DEFAULT 0
started_at             TIMESTAMP nullable
finished_at            TIMESTAMP nullable
error_message          TEXT nullable
created_at, updated_at

INDEX (client_id, status)
INDEX (client_id, created_at)
```

### 4.4 crawled_pages

The hot table. Contains the last N crawl runs per client. Older rows are moved to `crawled_pages_archive` nightly by `PruneOldCrawlRunsJob`.

```sql
id
crawl_run_id             FK -> crawl_runs.id CASCADE DELETE
client_id                FK -> clients.id CASCADE DELETE
url                      TEXT
status_code              SMALLINT UNSIGNED nullable
redirect_url             TEXT nullable
redirect_count           TINYINT UNSIGNED DEFAULT 0
canonical_url            TEXT nullable
canonical_is_self        BOOLEAN nullable
meta_title               VARCHAR(512) nullable
meta_title_length        SMALLINT UNSIGNED DEFAULT 0
meta_description         VARCHAR(1024) nullable
meta_description_length  SMALLINT UNSIGNED DEFAULT 0
h1                       VARCHAR(512) nullable
h1_count                 TINYINT UNSIGNED DEFAULT 0
word_count               SMALLINT UNSIGNED DEFAULT 0
is_indexable             BOOLEAN DEFAULT true
in_sitemap               BOOLEAN DEFAULT false
has_schema_markup        BOOLEAN DEFAULT false
schema_types             JSON nullable       -- ["LocalBusiness","Service"]
internal_links_count     SMALLINT UNSIGNED DEFAULT 0
external_links_count     SMALLINT UNSIGNED DEFAULT 0
broken_links_count       SMALLINT UNSIGNED DEFAULT 0
response_time_ms         SMALLINT UNSIGNED nullable
page_hash                CHAR(64) nullable   -- SHA-256 of raw HTML body
first_seen_at            TIMESTAMP nullable
last_seen_at             TIMESTAMP nullable
created_at, updated_at

INDEX (client_id, crawl_run_id)
INDEX (crawl_run_id, status_code)
INDEX (client_id, url(255))
```

### 4.5 crawled_pages_archive

Identical column structure to `crawled_pages` with two differences:
- No foreign key constraints (the referenced crawl_run may have been deleted)
- Additional column: `archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP INDEX`

`client_id` and `crawl_run_id` are plain `BIGINT UNSIGNED`, not foreign keys.

```sql
INDEX (client_id, crawl_run_id)
INDEX (archived_at)
```

### 4.6 crawl_issues

```sql
id
client_id       FK -> clients.id CASCADE DELETE
crawl_run_id    FK -> crawl_runs.id CASCADE DELETE
url             TEXT
issue_type      VARCHAR(100) INDEX    -- short class name, e.g. "MissingTitleCheck"
severity        ENUM('critical','warning','info') INDEX
context         JSON nullable         -- flexible payload per issue type
detected_at     TIMESTAMP
resolved_at     TIMESTAMP nullable    INDEX
alerted_at      TIMESTAMP nullable
created_at, updated_at

INDEX (client_id, severity, resolved_at)
INDEX (client_id, issue_type, resolved_at)
```

### 4.7 page_screenshots

```sql
id
client_id        FK -> clients.id CASCADE DELETE
url              TEXT
file_path        VARCHAR     -- storage disk path, e.g. "screenshots/client-1/homepage/2024-01-15.jpg"
disk             VARCHAR DEFAULT 'local'
siteshot_job_id  VARCHAR nullable
viewport_width   SMALLINT UNSIGNED DEFAULT 1440
full_page        BOOLEAN DEFAULT true
captured_at      TIMESTAMP
notes            TEXT nullable
created_at, updated_at

INDEX (client_id, url(255), captured_at)
```

---

## 5. Settings Resolution

`ClientSettings::for(Client $client): array` merges client settings over global defaults. Always call this before building a crawler instance or running analysis.

```php
// app/Crawlers/ClientSettings.php
public static function for(Client $client): array
{
    $global = CrawlerSetting::current(); // single cached row

    $defaults = [
        'crawl_frequency_hours'      => $global->default_crawl_frequency_hours,
        'screenshot_frequency_hours' => $global->default_screenshot_frequency_hours,
        'max_depth'                  => $global->default_max_depth,
        'crawl_limit'                => $global->default_crawl_limit,
        'concurrency'                => $global->default_concurrency,
        'slow_response_threshold_ms' => $global->default_slow_response_threshold_ms,
        'thin_content_threshold'     => $global->default_thin_content_threshold,
        'alert_on_severity'          => $global->alert_on_severity,
        'monitored_urls'             => ['/'],
        'excluded_patterns'          => [
            '/wp-admin', '/wp-login', '/wp-json',
            '?s=', '/feed', '/page/',
            '.xml', '.pdf', '.jpg', '.png', '.gif', '.css', '.js',
        ],
    ];

    return array_merge($defaults, $client->settings ?? []);
}

// CrawlerSetting::current() helper
public static function current(): self
{
    return static::first() ?? static::create([]);
}
```

---

## 6. Crawl Engine

### 6.1 ClientCrawlProfile

Implements `Spatie\Crawler\CrawlProfiles\CrawlProfile`. Controls which URLs the crawler follows.

Rules:
- Only follow URLs whose host matches the client's domain host
- Skip any URL that contains a string from `settings['excluded_patterns']`
- All other internal URLs are crawled

```php
public function shouldCrawl(UriInterface $url): bool
{
    if ($url->getHost() !== $this->baseHost) return false;

    foreach ($this->excludedPatterns as $pattern) {
        if (str_contains((string) $url, $pattern)) return false;
    }

    return true;
}
```

### 6.2 ClientCrawlObserver

Implements `Spatie\Crawler\CrawlObservers\CrawlObserver`. Called for every page the crawler visits. Responsible only for extraction and persistence. Does not analyze or flag issues.

Extracted fields per page:

| Field | Source | Notes |
|---|---|---|
| `url` | PSR-7 UriInterface | Final URL after redirects |
| `status_code` | PSR-7 ResponseInterface | HTTP status |
| `redirect_url` | PSR-7 | Final URL if redirected |
| `redirect_count` | ResponseInterface history | Depth of redirect chain |
| `canonical_url` | `<link rel="canonical" href="...">` | Raw href value |
| `canonical_is_self` | comparison | `canonical_url === url` |
| `meta_title` | `<title>` first match | Trimmed text |
| `meta_title_length` | `mb_strlen(meta_title)` | |
| `meta_description` | `<meta name="description" content="...">` | Trimmed content attr |
| `meta_description_length` | `mb_strlen(meta_description)` | |
| `h1` | `<h1>` first match | Trimmed text |
| `h1_count` | count of `<h1>` elements | |
| `word_count` | `str_word_count(strip_tags(body_html))` | Whitespace normalized |
| `is_indexable` | `<meta name="robots">` content | False if contains "noindex" |
| `has_schema_markup` | `@itemtype` attrs OR `<script type="application/ld+json">` | Boolean |
| `schema_types` | extracted from above | Array of type names, e.g. `["LocalBusiness"]` |
| `internal_links_count` | `<a href>` where host matches domain | |
| `external_links_count` | `<a href>` where host differs | |
| `response_time_ms` | `microtime(true)` diff around DOM parse | Approximate |
| `page_hash` | `hash('sha256', raw_html_body)` | Change detection |
| `first_seen_at` / `last_seen_at` | `now()` | Set on insert |

For `crawlFailed()`, write a minimal row with `url`, `status_code` (from exception response if available, else 0), and timestamps.

Use Symfony DomCrawler for all HTML parsing: `new Symfony\Component\DomCrawler\Crawler($html)`.

### 6.3 CrawlClientJob

Queue: `crawl`. Timeout: 3600 seconds. Tries: 2.

```
1. Call ClientSettings::for($client)
2. Create CrawlRun record (status: pending)
3. Call $run->markRunning()
4. Instantiate ClientCrawlObserver and ClientCrawlProfile
5. Build Spatie\Crawler\Crawler:
   - setCrawlObserver($observer)
   - setCrawlProfile($profile)
   - setMaximumDepth($settings['max_depth'])
   - setTotalCrawlLimit($settings['crawl_limit'])
   - setConcurrency($settings['concurrency'])
   - setDelayBetweenRequests(250)  -- polite crawling
6. startCrawling($client->resolvedDomain())
7. Call $run->markCompleted(['pages_crawled' => $observer->getPagesCrawled()])
8. Update $client->last_crawled_at = now()
9. Dispatch AnalyzeCrawlRunJob on 'default' queue
10. On any exception: $run->markFailed($e->getMessage()), rethrow
```

`Client::resolvedDomain()` returns `rtrim($this->domain, '/')`.

---

## 7. Issue Analyzer

### 7.1 CrawlCheck Interface

```php
interface CrawlCheck
{
    public function run(
        CrawlRun $crawlRun,
        Client $client,
        Collection $currentPages,  // all crawled_pages for this run
        Collection $previousPages, // all crawled_pages for previous completed run
        array $settings            // resolved ClientSettings
    ): Collection; // Collection of arrays (not models), ready for bulk insert
}
```

### 7.2 BuildsIssues Trait

Used by all checks to produce consistently shaped arrays:

```php
protected function issue(
    CrawlRun $crawlRun,
    Client $client,
    string $url,
    string $issueType,
    string $severity,    // 'critical', 'warning', 'info'
    array $context = []
): array {
    return [
        'client_id'    => $client->id,
        'crawl_run_id' => $crawlRun->id,
        'url'          => $url,
        'issue_type'   => $issueType,
        'severity'     => $severity,
        'context'      => json_encode($context),
        'detected_at'  => now(),
        'created_at'   => now(),
        'updated_at'   => now(),
    ];
}
```

### 7.3 IssueAnalyzer

Instantiates all checks and runs them in sequence. Wraps each check in try/catch so one failing check does not abort the rest. Logs errors with `check class name` and `client_id`.

Returns a flat `Collection` of all issue arrays across all checks.

### 7.4 AnalyzeCrawlRunJob

Queue: `default`. Tries: 2.

```
1. Load currentPages: CrawledPage::where('crawl_run_id', $run->id)->get()
2. Load previousRun: most recent completed run for this client, excluding current
3. Load previousPages: CrawledPage::where('crawl_run_id', $previousRun->id)->get()
   -- empty Collection if no previous run exists
4. Run IssueAnalyzer::run($run, $client, $currentPages, $previousPages)
5. Bulk insert new issues: CrawlIssue::insert($newIssues->toArray())
6. Auto-resolve stale issues:
   -- Mark resolved_at = now() on open issues for this client where:
   -- url is no longer in current crawl's URLs
   -- OR issue_type no longer appears in the new issue set for that URL
7. Update $run with issue count fields
8. Dispatch AlertCriticalIssuesJob on 'default' queue
```

### 7.5 Check Specifications

Every check receives `currentPages`, `previousPages`, and `settings`. All checks filter to `is_indexable = true` unless checking non-indexable conditions.

#### HomepageDownCheck — severity: critical
Find the homepage page (URL equals `$client->resolvedDomain() . '/'` or first page if not found). Flag if `status_code < 200 OR status_code >= 300`. Context: `{status_code}`.

#### BrokenLinksCheck — severity: critical (5xx) or warning (4xx)
All pages where `status_code >= 400`. 5xx = critical, 4xx = warning. Context: `{status_code}`.

#### NoindexOnMonitoredUrlCheck — severity: critical
For each URL in `settings['monitored_urls']`, resolve to full URL. Find matching crawled page. Flag if `is_indexable === false`. Context: `{monitored_url}`.

#### SiteWideNoindexCheck — severity: critical
If the ratio of non-indexable pages to total pages is >= 80%, flag at the domain level. Context: `{noindex_ratio, pages_checked}`.

#### MissingTitleCheck — severity: warning
Indexable pages where `meta_title` is null or empty string.

#### DuplicateTitleCheck — severity: warning
Group indexable pages by `meta_title`. If 3 or more pages share the same title, flag each. Context: `{title, shared_with_count}`.

#### TitleTooLongCheck — severity: info
Indexable pages where `meta_title_length > 60`. Context: `{length, title}`.

#### TitleTooShortCheck — severity: info
Indexable pages where `meta_title_length > 0 AND meta_title_length < 30`. Context: `{length, title}`.

#### MissingMetaDescriptionCheck — severity: warning
Indexable pages where `meta_description` is null or empty.

#### MissingH1Check — severity: warning
Indexable pages where `h1_count === 0`.

#### MultipleH1Check — severity: info
Indexable pages where `h1_count > 1`. Context: `{h1_count}`.

#### ThinContentCheck — severity: warning
Indexable pages where `word_count > 0 AND word_count < settings['thin_content_threshold']`. Context: `{word_count, threshold}`.

#### SlowPageCheck — severity: warning
Pages where `response_time_ms > settings['slow_response_threshold_ms']`. Context: `{response_time_ms, threshold_ms}`.

#### RedirectChainCheck — severity: warning
Pages where `redirect_count > 1`. Context: `{redirect_count, final_url}`.

#### CanonicalMismatchCheck — severity: info
Pages where `canonical_url` is set and `canonical_is_self === false`. Context: `{canonical_url}`.

#### PageDisappearedCheck — severity: warning
For each previous page that was indexable and had status_code < 400: if that URL is absent from the current crawl entirely, or is present with status_code 404, flag it. Context: `{last_seen_status_code}` or `{current_status_code}`.

#### NewPagesDetectedCheck — severity: info
Skip if `previousPages` is empty (first crawl). Flag pages in current run with `status_code === 200` whose URL does not appear in `previousPages`.

---

## 8. Job: AlertCriticalIssuesJob

Queue: `default`. Tries: 3.

```
1. Load ClientSettings for client
2. Query CrawlIssue where:
   - crawl_run_id = $run->id
   - severity IN settings['alert_on_severity']
   - alerted_at IS NULL
3. If none, return early
4. Load global CrawlerSetting for webhook URL
5. Determine Slack channel: $client->slack_channel ?? $global->slack_default_channel
6. If no webhook URL, return early
7. Build Slack Block Kit payload (see below)
8. POST to webhook via Http::post()
9. Mark all $newIssues->each->update(['alerted_at' => now()])
```

### Slack Message Format

Header block: "🚨 Critical SEO Issues: {client name}"

Section block with mrkdwn:
```
*N critical issue(s) detected* on <domain|domain>

:red_circle: *IssuTypeName* — `url`
:red_circle: *IssueTypeName* — `url`
```

Context block: "Crawl run #N — Jan 1, 2024 9:00 am"

---

## 9. Job: ScreenshotClientJob

Queue: `crawl`. Timeout: 300 seconds. Tries: 2.

For each URL in `settings['monitored_urls']`:
```
1. Resolve full URL: domain + '/' + ltrim(path, '/')
2. Generate file path: "screenshots/client-{id}/{slug}/{date}.jpg"
   where slug = Str::slug(parse_url(url, PHP_URL_PATH)) or 'homepage'
   and date = now()->format('Y-m-d')
3. GET https://api.siteshot.app/v1/screenshot with params:
   token, url, width=1440, full_page=1, format=jpg, quality=85
4. On success: Storage::disk($disk)->put($filePath, $response->body())
5. Create PageScreenshot record
6. On failure: Log::warning() and continue to next URL (don't fail whole job)
```

After all URLs: `$client->update(['last_screenshot_at' => now()])`.

The SiteShot API key comes from `config('services.siteshot.key')` which reads `SITESHOT_API_KEY` from `.env`. Verify the exact SiteShot API endpoint and parameter names against their documentation before implementing.

The storage disk name comes from `config('filesystems.screenshots_disk', 'local')`. Set `SCREENSHOTS_DISK=local` in `.env` (or `s3` etc).

---

## 10. Job: PruneOldCrawlRunsJob

Runs nightly at 02:00. Queue: `default`.

### Crawl Run Pruning

For each active client:
1. Get the IDs of the most recent `crawl_runs_to_keep` completed runs
2. If fewer than `crawl_runs_to_keep` completed runs exist, skip this client
3. Find all other completed run IDs for this client (older ones)
4. Use raw SQL `INSERT INTO crawled_pages_archive SELECT *, NOW() FROM crawled_pages WHERE crawl_run_id IN (...)`
5. Delete from `crawled_pages` where `crawl_run_id IN (...)`
6. Delete the `CrawlRun` records themselves

### Issue Pruning

Delete from `crawl_issues` where `resolved_at IS NOT NULL AND resolved_at < now() - interval N days`, where N = `resolved_issues_retention_days` from global settings.

---

## 11. Job: WeeklySummaryJob

Fires every Monday at 08:00. Queue: `default`.

Aggregates:
- Total active clients
- Open critical issue count
- Open warning issue count
- Issues resolved in the last 7 days
- Count of clients with at least one open critical issue
- Clients with zero open issues ("clean" clients)

Posts to `slack_webhook_url` using `slack_default_channel`. Returns early if no webhook configured.

Slack format: header + a 2-column section fields block with all stats.

---

## 12. Scheduling

In `routes/console.php`:

```php
Schedule::command('watchdog:dispatch-crawls')->everyFifteenMinutes();
Schedule::command('watchdog:dispatch-screenshots')->hourly();
Schedule::job(new PruneOldCrawlRunsJob)->dailyAt('02:00');
Schedule::job(new WeeklySummaryJob)->weeklyOn(1, '08:00');
```

### DispatchPendingCrawls Command

Signature: `watchdog:dispatch-crawls`

Finds up to 5 active clients where `last_crawled_at IS NULL OR last_crawled_at <= NOW() - INTERVAL N HOUR` (N from client settings, falling back to global default).

Uses MySQL JSON path: `COALESCE(JSON_UNQUOTE(JSON_EXTRACT(settings, '$.crawl_frequency_hours')), ?)`.

Orders by `last_crawled_at ASC` (oldest crawl first). Dispatches `CrawlClientJob` for each.

### DispatchPendingScreenshots Command

Signature: `watchdog:dispatch-screenshots`

Same pattern as above but using `last_screenshot_at` and `screenshot_frequency_hours`. Dispatches up to 3 per run.

---

## 13. Queue Configuration (Horizon)

```php
// config/horizon.php (production environment)
'crawlers' => [
    'connection'  => 'redis',
    'queue'       => ['crawl'],
    'balance'     => 'simple',
    'processes'   => 3,     // max 3 concurrent crawl/screenshot jobs
    'tries'       => 2,
    'timeout'     => 3600,
],
'default' => [
    'connection'   => 'redis',
    'queue'        => ['default'],
    'balance'      => 'auto',
    'minProcesses' => 1,
    'maxProcesses' => 5,
    'tries'        => 3,
],
```

Ploi manages Horizon as a daemon service. After deployment, restart via Ploi's "Daemons" panel.

---

## 14. Eloquent Models

### CrawlerSetting

- `$fillable`: all columns except `id` and timestamps
- `$casts`: `alert_on_severity => 'array'`
- Static method `current(): self` returns `first() ?? create([])`

### Client

- `$casts`: `is_active => 'boolean'`, `last_crawled_at => 'datetime'`, `last_screenshot_at => 'datetime'`, `settings => 'array'`
- Relationships: `crawlRuns()`, `latestCrawlRun()`, `crawledPages()`, `crawlIssues()`, `openIssues()`, `screenshots()`
- Scopes: `scopeActive()`, `scopeDueForCrawl()`, `scopeDueForScreenshot()`
- Helpers: `resolvedDomain(): string`, `openCriticalIssuesCount(): int`

### CrawlRun

- `$casts`: `triggered_manually => 'boolean'`, `started_at => 'datetime'`, `finished_at => 'datetime'`
- Relationships: `client()`, `pages()`, `issues()`
- Methods: `markRunning()`, `markCompleted(array $counts)`, `markFailed(string $error)`, `durationSeconds(): ?int`

### CrawledPage

- `$casts`: all boolean fields, `schema_types => 'array'`, date fields
- Relationships: `crawlRun()`, `client()`
- Helpers: `isOk(): bool`, `isBroken(): bool`, `isRedirect(): bool`

### CrawlIssue

- `$casts`: `context => 'array'`, all timestamp fields
- Relationships: `client()`, `crawlRun()`
- Scopes: `scopeOpen()`, `scopeCritical()`, `scopeUnalerted()`
- Methods: `isResolved(): bool`, `markResolved(): void`

### PageScreenshot

- `$casts`: `full_page => 'boolean'`, `captured_at => 'datetime'`
- Relationships: `client()`
- Method: `publicUrl(): string` -- returns `Storage::disk($this->disk)->url($this->file_path)`

---

## 15. Filament v5 Admin Panel

Panel path: `/admin`. No authentication beyond the standard Filament guard (single user, no roles needed). Set up with `php artisan filament:install --panels`.

### Navigation Structure

```
Clients (group)
  Clients                  -- ClientResource (sort: 1)

Crawl Data (group)
  Crawl Runs               -- CrawlRunResource (sort: 2)
  Issues                   -- CrawlIssueResource (sort: 3)  [badge: open critical count]
  Screenshots              -- PageScreenshotResource (sort: 4)

Configuration (group)
  Global Settings          -- CrawlerSettingsPage (sort: 10)
```

### 15.1 ClientResource

List columns:
- `name` (sortable, searchable)
- `domain` (clickable link, opens in new tab, truncated to 40 chars)
- `is_active` (icon boolean)
- `openIssues` count (badge: danger if >= 5, warning if >= 1, success if 0)
- `last_crawled_at` (since, sortable)

Table actions per row:
- "Crawl Now" button (green, play icon, requires confirmation, dispatches `CrawlClientJob` with `triggeredManually: true`, shows Filament success notification)
- Edit
- View

Filters: `TernaryFilter` on `is_active`

Default sort: `name ASC`

Form schema:
```
Section: Client Details (2 columns)
  name (required)
  slug (required, unique ignoring self, helper text: "URL-safe identifier")
  domain (required, URL validation, placeholder "https://example.com")
  is_active (toggle, default true)
  slack_channel (optional, placeholder "#client-alerts")
  notes (textarea, full width)

Section: Crawl Settings (2 columns, description: "Leave blank to use global defaults")
  settings.crawl_frequency_hours (numeric)
  settings.max_depth (numeric)
  settings.crawl_limit (numeric)
  settings.concurrency (numeric)
  settings.slow_response_threshold_ms (numeric)
  settings.thin_content_threshold (numeric)

Section: Monitored URLs
  Repeater on settings.monitored_urls
  Simple repeater (single TextInput per item, placeholder "/services")
  Add label: "Add URL"

Section: Excluded URL Patterns
  Repeater on settings.excluded_patterns
  Simple repeater (single TextInput per item, placeholder "/wp-admin")
  Add label: "Add pattern"
```

### 15.2 ViewClient Page

Header actions: "Crawl Now" button (same as table action) + "Edit" button.

Infolist:
```
Section: Overview (4 columns)
  domain (clickable link)
  last_crawled_at (since)
  is_active (icon boolean)
  open critical issues count (badge: danger if > 0, success if 0)
```

### 15.3 CrawlRunResource

Read-only (no form, no create). List only.

Columns:
- `client.name` (sortable, searchable)
- `status` (badge: gray=pending, warning=running, success=completed, danger=failed)
- `triggered_manually` (icon boolean, label "Manual")
- `pages_crawled`
- `critical_issues_found` (badge: danger if > 0, success if 0)
- `warning_issues_found` (badge: warning if > 0, success if 0)
- `started_at` (since, sortable)
- Duration (computed: `durationSeconds()` + "s", or blank if null)

Filters: status select, client relationship select

Default sort: `started_at DESC`

### 15.4 CrawlIssueResource

Navigation badge: count of open critical issues. Badge color: `danger`.

Read-only list (no form, no create).

Columns:
- `client.name` (sortable, searchable)
- `severity` (badge: danger=critical, warning=warning, info=info)
- `issue_type` (badge, color gray)
- `url` (truncated to 60 chars, full URL in tooltip)
- `detected_at` (since, sortable)
- `resolved_at` presence (icon boolean, check=resolved, x=open)

Row actions:
- "Mark Resolved" (visible only when `resolved_at IS NULL`, calls `$record->markResolved()`)

Filters:
- Severity select (critical/warning/info)
- Client relationship select
- Issue type select (populated from distinct `issue_type` values in DB)
- "Open only" filter (whereNull('resolved_at')) -- DEFAULT ACTIVE

Default sort: `detected_at DESC`

### 15.5 PageScreenshotResource

Read-only list (no form, no create).

Columns:
- `client.name` (sortable, searchable)
- Screenshot thumbnail (`ImageColumn` using `publicUrl()`, height 60px)
- `url` (truncated to 50 chars)
- `captured_at` (since, sortable)

Filters: client relationship select

Default sort: `captured_at DESC`

### 15.6 CrawlerSettingsPage

Custom Filament Page (not a Resource). Route: `/admin/settings`.

Single-record form bound to `CrawlerSetting::current()`. Loads data in `mount()` using `$this->form->fill($settings->toArray())`.

Save action calls `CrawlerSetting::current()->update($this->form->getState())` and shows success notification.

Form schema:
```
Section: Crawl Defaults (2 columns)
  default_crawl_frequency_hours (required, numeric)
  default_max_depth (required, numeric)
  default_crawl_limit (required, numeric)
  default_concurrency (required, numeric)
  default_slow_response_threshold_ms (required, numeric)
  default_thin_content_threshold (required, numeric)

Section: Data Retention (2 columns)
  crawl_runs_to_keep (required, numeric)
  resolved_issues_retention_days (required, numeric)

Section: Screenshot Defaults
  default_screenshot_frequency_hours (required, numeric)

Section: Slack Notifications (2 columns)
  slack_webhook_url (URL validation, full width)
  slack_default_channel (placeholder "#seo-watchdog")
  alert_on_severity (CheckboxList: critical/warning/info)
```

---

## 16. Environment Variables

Add these to `.env` (and `.env.example`):

```env
# SiteShot
SITESHOT_API_KEY=your_key_here

# Screenshot storage disk (local, s3, do_spaces, etc.)
SCREENSHOTS_DISK=local

# Redis (required for Horizon)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue driver must be redis for Horizon
QUEUE_CONNECTION=redis
```

Add to `config/services.php`:
```php
'siteshot' => [
    'key' => env('SITESHOT_API_KEY'),
],
```

Add to `config/filesystems.php` disks array if using non-default disk:
```php
'screenshots_disk' => env('SCREENSHOTS_DISK', 'local'),
```

---

## 17. Known Limitations and Edge Cases

### MySQL vs Postgres

The `scopeDueForCrawl` and `scopeDueForScreenshot` scopes on `Client` use MySQL's `JSON_UNQUOTE(JSON_EXTRACT(...))` syntax. If the database is ever migrated to Postgres, replace with `settings->>'crawl_frequency_hours'`.

### response_time_ms Accuracy

The `response_time_ms` recorded in `ClientCrawlObserver` is measured as the time to parse the HTML body using `microtime()` differencing, not true network TTFB. It is a useful relative signal (slow pages will still appear slower than fast ones) but should be labeled "crawl response time" in the UI rather than TTFB. Future improvement: hook into Guzzle transfer stats via middleware.

### SiteShot API Endpoint

The SiteShot endpoint `https://api.siteshot.app/v1/screenshot` and parameter names in `ScreenshotClientJob` are based on available documentation at time of writing. Verify against current SiteShot API docs before implementing and adjust endpoint/params accordingly.

### JavaScript-Rendered Content

spatie/crawler performs standard HTTP requests. It does not execute JavaScript. All 50 client sites are WordPress installations with server-side rendering, so this is not a problem. If any client uses a JavaScript-heavy page builder that defers content to client-side rendering, signals like `word_count` and `h1_count` may be inaccurate for those pages. The solution would be enabling spatie/crawler's Browsershot support, which requires Chrome + Puppeteer on the server.

### Archive Table Insert Performance

`PruneOldCrawlRunsJob` uses a raw `INSERT INTO ... SELECT` for bulk archiving. This is efficient for large row sets but acquires a table lock. If the crawled_pages table is large, run pruning during off-hours (2am is the current schedule) and monitor for lock contention.

### Duplicate Issue Detection

The auto-resolve logic in `AnalyzeCrawlRunJob` resolves issues by URL + issue_type. This means if a check fires for the same URL and issue_type in consecutive runs, it will appear as resolved and re-opened each time. This is intentional: it prevents stale open issues from accumulating. If persistent open issue tracking (single record per URL+type that stays open across runs) is ever needed, the resolution logic should be reworked to match on URL+type across runs rather than treating each run as a fresh set.

---

## 18. Build Order

Build in this sequence to get something testable in production as fast as possible. Each phase is independently deployable.

### Phase 1 — Data Layer (Day 1)
1. Create Laravel project: `laravel new cgn-watchdog`
2. Run `composer require spatie/crawler filament/filament:"^5.0" laravel/horizon`
3. Create all 7 migrations and run them
4. Create all 6 Eloquent models
5. Create `ClientSettings` resolver
6. Seed one test client via `php artisan tinker`

### Phase 2 — Crawl Core (Day 1-2)
1. Implement `ClientCrawlProfile`
2. Implement `ClientCrawlObserver`
3. Implement `CrawlClientJob`
4. Test with: `CrawlClientJob::dispatchSync($client)` in Tinker
5. Verify `crawled_pages` rows are being written correctly

### Phase 3 — Analysis + Alerts (Day 2-3)
1. Implement `CrawlCheck` interface and `BuildsIssues` trait
2. Implement the 5 core checks: `HomepageDownCheck`, `BrokenLinksCheck`, `NoindexOnMonitoredUrlCheck`, `MissingTitleCheck`, `PageDisappearedCheck`
3. Implement `IssueAnalyzer`
4. Implement `AnalyzeCrawlRunJob`
5. Implement `AlertCriticalIssuesJob`
6. Test full chain: crawl -> analyze -> check slack

### Phase 4 — Filament UI (Day 3-4)
1. Run `php artisan filament:install --panels`
2. Install Tailwind v4 per Filament v5 docs
3. Implement `ClientResource` (list + form + view page with "Crawl Now" button)
4. Implement `CrawlRunResource` (list only)
5. Implement `CrawlIssueResource` (list with resolve action)
6. Implement `CrawlerSettingsPage`

### Phase 5 — Scheduler + Scale (Day 4)
1. Implement `DispatchPendingCrawls` and `DispatchPendingScreenshots` commands
2. Configure `routes/console.php` schedule
3. Configure Horizon (`config/horizon.php`)
4. Install Horizon on Ploi as a daemon
5. Deploy and roll out to all 50 clients

### Phase 6 — Screenshots (Day 5)
1. Implement `ScreenshotClientJob`
2. Implement `PageScreenshotResource` in Filament
3. Configure SiteShot API key in `.env`
4. Test screenshot capture on one client

### Phase 7 — Polish (Ongoing)
1. Implement remaining 11 checks (see Section 7.5)
2. Implement `PruneOldCrawlRunsJob`
3. Implement `WeeklySummaryJob`
4. Add `PageDisappearedCheck` and `NewPagesDetectedCheck` (require previous run data)
5. Add Filament Dashboard widgets (stats overview, open issues chart)

---

## 19. Filament Dashboard Widgets (Phase 7)

Add to the Filament panel dashboard:

- `StatsOverviewWidget`: Total active clients, Open critical issues (danger color if > 0), Open warnings, Issues resolved today
- A bar or line chart of crawl run counts and issue counts over the last 14 days (use Filament's built-in chart widgets with a `CrawlRun` dataset)
- A table widget: "Latest Crawl Runs" showing the 10 most recent runs across all clients

---

## 20. Deployment Notes (Ploi + DigitalOcean)

1. Redis must be installed on the droplet. Install via Ploi's "Services" or manually: `sudo apt install redis-server`
2. After deploying, run: `php artisan migrate --force`
3. Set up the scheduler in Ploi's "Scheduler" panel: `php artisan schedule:run` every minute
4. Add Horizon as a daemon in Ploi: `php artisan horizon` with restart on failure
5. After each deployment: Ploi's deployment hook should run `php artisan horizon:terminate` to gracefully restart Horizon workers with new code
6. Screenshots disk: if using local storage, ensure the storage directory is writable and linked (`php artisan storage:link`). For DO Spaces, configure the `s3` driver with DO Spaces credentials and set `SCREENSHOTS_DISK=s3`
