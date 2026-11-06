<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Statamic\Facades\Entry;

/**
 * Runs one step of demo:compare in its own PHP process and prints how long it took and how
 * much memory it used, as JSON. Not meant to be run by hand.
 */
class DemoMeasure extends Command
{
    protected $signature = 'demo:measure {step : build, save or urls}';

    protected $description = 'Time one step for demo:compare (internal)';

    protected $hidden = true;

    public function handle(): int
    {
        if ($this->argument('step') === 'urls') {
            $this->line(json_encode($this->urls()));

            return self::SUCCESS;
        }

        $flatpack = (bool) config('flatpack.enabled');
        $t = hrtime(true);

        match ($this->argument('step')) {
            // What each setup runs at deploy.
            'build' => $flatpack ? Artisan::call('flatpack:build') : (Artisan::call('statamic:stache:clear') + Artisan::call('statamic:stache:warm')),
            'save' => $this->save(),
        };

        $this->line(json_encode([
            'ms' => round((hrtime(true) - $t) / 1e6, 1),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]));

        return self::SUCCESS;
    }

    /** Edit and save a book, as the Control Panel does, then put its file back as it was. */
    private function save(): void
    {
        $entry = Entry::query()->where('collection', 'books')->where('site', 'en')->orderBy('id')->first();
        $path = $entry->path();
        $before = file_get_contents($path);

        $entry->set('summary', 'Edited by demo:compare at '.now()->toIso8601String())->save();

        // Back as generated. demo:compare refreshes afterwards, so the compiled copy follows.
        file_put_contents($path, $before);
    }

    /** A sample of every kind of page, picked the same way every time. */
    private function urls(): array
    {
        $book = Entry::query()->where('collection', 'books')->where('site', 'en')->where('published', true)->orderBy('id')->first();
        $post = Entry::query()->where('collection', 'journal')->where('site', 'en')->orderBy('id')->first();
        $author = \Statamic\Facades\Term::find('authors::'.($book->get('authors')[0] ?? ''));

        return [
            'home' => '/',
            'book list (page 3)' => '/books?page=3',
            'book' => $book->uri(),
            'journal post' => $post->uri(),
            'author' => $author ? parse_url($author->url(), PHP_URL_PATH) : '/authors',
            'subject' => '/subjects/poetry',
        ];
    }
}
