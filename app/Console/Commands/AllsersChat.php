<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use App\Tools\SystemInfoTool;
use Illuminate\Support\Facades\Cache;

class AllsersChat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactive chat with AI using Prism';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $provider = Provider::from(config('services.ai.provider'));
        $model = config('services.ai.model');
        $messages = [
            new SystemMessage('You are a helpful assistant.'),
        ];

        $this->info('Welcome to Allsers AI Chat! (Type "exit" to quit)');

        while (true) {
            $question = $this->ask('You');

            if (strtolower($question) === 'exit' || empty($question)) {
                $this->info('Goodbye!');
                break;
            }

            $messages[] = new UserMessage($question);

            $this->output->write('<fg=yellow>Thinking...</>');

            try {
                // 1. Global Daily Quota Check
                $dayKey = 'lila_global_daily_quota:' . date('Y-m-d');
                $dailyCount = Cache::get($dayKey, 0);
                if ($dailyCount >= 950) {
                    $this->error("\nGlobal daily quota reached (950/1000). Please try again tomorrow.");
                    break;
                }

                $response = Prism::text()
                    ->using($provider, $model)
                    ->withMessages($messages)
                    ->withTools([new SystemInfoTool()])
                    ->withMaxSteps(5)
                    ->asText();

                // Clear "Thinking..."
                $this->output->write("\r" . str_repeat(' ', 20) . "\r");

                $this->line('<fg=green>Assistant:</> ' . $response->text);

                $messages[] = new AssistantMessage($response->text);

                // Increment quota on success
                Cache::put($dayKey, $dailyCount + 1, now()->addDay());
            } catch (\Exception $e) {
                $this->error("\nError: " . $e->getMessage());
            }
        }
    }
}
