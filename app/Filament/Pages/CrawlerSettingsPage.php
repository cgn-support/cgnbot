<?php

namespace App\Filament\Pages;

use App\Models\CrawlerSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * @property Schema $form
 */
class CrawlerSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Global Settings';

    protected static ?string $title = 'Global Settings';

    protected string $view = 'filament.pages.crawler-settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = CrawlerSetting::current();
        $this->form->fill($settings->toArray());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Crawl Defaults')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('default_crawl_frequency_hours')
                            ->label('Crawl Frequency (hours)')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('default_max_depth')
                            ->label('Max Depth')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('default_crawl_limit')
                            ->label('Crawl Limit')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('default_concurrency')
                            ->label('Concurrency')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('default_slow_response_threshold_ms')
                            ->label('Slow Response Threshold (ms)')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('default_thin_content_threshold')
                            ->label('Thin Content Threshold')
                            ->required()
                            ->numeric(),
                    ]),

                Section::make('Data Retention')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('crawl_runs_to_keep')
                            ->label('Crawl Runs to Keep')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('resolved_issues_retention_days')
                            ->label('Resolved Issues Retention (days)')
                            ->required()
                            ->numeric(),
                    ]),

                Section::make('Screenshot Defaults')
                    ->schema([
                        Forms\Components\TextInput::make('default_screenshot_frequency_hours')
                            ->label('Screenshot Frequency (hours)')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('default_visual_diff_threshold')
                            ->label('Visual Diff Threshold (%)')
                            ->required()
                            ->numeric(),
                    ]),

                Section::make('Slack Notifications')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('slack_webhook_url')
                            ->label('Webhook URL')
                            ->url()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('slack_default_channel')
                            ->label('Default Channel')
                            ->placeholder('#seo-watchdog'),
                        Forms\Components\CheckboxList::make('alert_on_severity')
                            ->label('Alert on Severity')
                            ->options([
                                'critical' => 'Critical',
                                'warning' => 'Warning',
                                'info' => 'Info',
                            ]),
                        Forms\Components\TextInput::make('alert_min_consecutive_detections')
                            ->label('Min Consecutive Detections')
                            ->helperText('Issues must be detected this many consecutive crawls before alerting (critical issues override with 1)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10),
                        Forms\Components\TextInput::make('alert_min_confidence')
                            ->label('Min Confidence (%)')
                            ->helperText('Issues below this confidence threshold will not trigger alerts')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        CrawlerSetting::current()->update($data);
        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }
}
