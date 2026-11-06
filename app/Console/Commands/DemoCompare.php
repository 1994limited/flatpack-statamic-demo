<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * The same site, measured twice: once with Statamic's own Stache, once with Flatpack.
 * Each step runs in a fresh PHP process, and pages are fetched from a real PHP server with
 * OPcache on, so the numbers are what a visitor's request costs.
 */
class DemoCompare extends Command
{
    protected $signature = 'demo:compare
        {--shape= : fixed, builder or nested (regenerates the content; default: what demo:content made last)}
        {--size= : small, medium, large or huge (regenerates the content)}
        {--all : every shape at every size up to --max-size, as one table}
        {--max-size=large : the biggest size --all goes to}
        {--runs=7 : requests per page for the warm timings (the median is reported)}';

    protected $description = 'Measure the demo shop with and without Flatpack, side by side';

    private const PORT = 8199;

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->all();
        }

        if ($this->option('shape') || $this->option('size')) {
            $last = $this->generated();
            $this->generate($this->option('shape') ?: ($last['shape'] ?? 'builder'), $this->option('size') ?: ($last['size'] ?? 'medium'));
        }

        if (! $generated = $this->generated()) {
            $this->components->error('No demo content yet. Run php artisan demo:content first, or pass --shape and --size.');

            return self::FAILURE;
        }

        $result = $this->compare();
        $this->table(['', 'Statamic', 'Flatpack', 'Change'], $this->rows($result));
        $this->save($generated, $result);

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- one comparison

    private function compare(): array
    {
        $g = $this->generated();
        $this->components->info("Dog-ear Books, {$g['shape']} and {$g['size']}: measuring Statamic, then Flatpack.");
        $urls = $this->urls();

        $out = [];
        foreach (['statamic' => false, 'flatpack' => true] as $mode => $flatpack) {
            $env = ['FLATPACK_ENABLED' => $flatpack ? 'true' : 'false'];

            $this->artisan(['statamic:stache:clear'], $env);
            File::deleteDirectory(storage_path('statamic/flatpack'));

            $this->components->task("{$mode}: build", function () use (&$out, $mode, $env) {
                $out[$mode]['build'] = $this->measure('build', $env);
            });

            $this->components->task("{$mode}: first request after a deploy (3 restarts)", function () use (&$out, $mode, $env, $urls) {
                $cold = [];
                foreach (range(1, 3) as $_) {
                    $server = $this->serve($env);
                    $cold[] = $this->get($urls['book']);
                    $server->stop();
                }
                $out[$mode]['first_request'] = $this->median($cold);
            });

            $this->components->task("{$mode}: page loads", function () use (&$out, $mode, $env, $urls) {
                $server = $this->serve($env);
                foreach ($urls as $name => $url) {
                    $this->get($url);
                    $this->get($url);
                    $out[$mode]['pages'][$name] = $this->median(array_map(fn () => $this->get($url), range(1, (int) $this->option('runs'))));
                }
                $server->stop();
            });

            $this->components->task("{$mode}: saving an entry", function () use (&$out, $mode, $env) {
                $out[$mode]['save'] = $this->measure('save', $env);
            });
        }

        // Put the saved book back into the compiled copy.
        $this->artisan(['flatpack:refresh'], ['FLATPACK_ENABLED' => 'true']);

        return $out;
    }

    private function rows(array $r): array
    {
        $s = $r['statamic'];
        $f = $r['flatpack'];
        $rows = [
            ['Build at deploy', $this->ms($s['build']['ms']), $this->ms($f['build']['ms']), $this->change($s['build']['ms'], $f['build']['ms'])],
            ['  peak memory', $s['build']['peak_mb'].' MB', $f['build']['peak_mb'].' MB', $this->change($s['build']['peak_mb'], $f['build']['peak_mb'], 'less', 'more')],
            ['First request after a deploy', $this->ms($s['first_request']), $this->ms($f['first_request']), $this->change($s['first_request'], $f['first_request'])],
        ];
        foreach ($s['pages'] as $page => $ms) {
            $rows[] = ["Page: {$page}", $this->ms($ms), $this->ms($f['pages'][$page]), $this->change($ms, $f['pages'][$page])];
        }
        $rows[] = ['Saving an entry', $this->ms($s['save']['ms']), $this->ms($f['save']['ms']), $this->change($s['save']['ms'], $f['save']['ms'])];

        return $rows;
    }

    // ---------------------------------------------------------------- every shape and size

    private function all(): int
    {
        $sizes = array_keys(DemoContent::SIZES);
        $sizes = array_slice($sizes, 0, array_search($this->option('max-size'), $sizes, true) + 1 ?: count($sizes));
        $rows = [];
        foreach (DemoContent::SHAPES as $shape) {
            foreach ($sizes as $size) {
                $this->generate($shape, $size);
                $r = $this->compare();
                $this->save($this->generated(), $r);
                $page = fn ($m) => $this->median(array_values($r[$m]['pages']));
                $rows[] = [
                    "{$shape} / {$size}",
                    $this->ms($r['statamic']['build']['ms']).' → '.$this->ms($r['flatpack']['build']['ms']),
                    $r['statamic']['build']['peak_mb'].' → '.$r['flatpack']['build']['peak_mb'].' MB',
                    $this->ms($r['statamic']['first_request']).' → '.$this->ms($r['flatpack']['first_request']),
                    $this->ms($page('statamic')).' → '.$this->ms($page('flatpack')),
                    $this->ms($r['statamic']['save']['ms']).' → '.$this->ms($r['flatpack']['save']['ms']),
                ];
            }
        }
        $this->newLine();
        $this->line('<options=bold>Statamic → Flatpack</>');
        $this->table(['Shape / size', 'Build', 'Build memory', 'First request', 'Typical page', 'Save'], $rows);

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- helpers

    private function generate(string $shape, string $size): void
    {
        $this->artisan(['demo:content', "--shape={$shape}", "--size={$size}"], [], true);
    }

    private function generated(): ?array
    {
        return is_file($f = storage_path('demo.json')) ? json_decode(file_get_contents($f), true) : null;
    }

    /** A sample of every kind of page, picked the same way every time (in a fresh process, so it sees the current content). */
    private function urls(): array
    {
        $this->artisan(['statamic:stache:clear'], ['FLATPACK_ENABLED' => 'false']);
        File::deleteDirectory(storage_path('statamic/flatpack'));

        return json_decode(trim(collect(explode("\n", trim($this->artisan(['demo:measure', 'urls'], ['FLATPACK_ENABLED' => 'false'])->getOutput())))->last()), true);
    }

    private function measure(string $step, array $env): array
    {
        $p = $this->artisan(['demo:measure', $step], $env);

        return json_decode(trim(collect(explode("\n", trim($p->getOutput())))->last()), true);
    }

    private function artisan(array $args, array $env = [], bool $show = false): Process
    {
        $p = new Process([PHP_BINARY, '-d', 'memory_limit=-1', base_path('artisan'), ...$args], base_path(), $env + ['APP_ENV' => 'local'], null, 3600);
        $p->mustRun();
        if ($show) {
            $this->line(trim($p->getOutput()));
        }

        return $p;
    }

    /** A real PHP server with OPcache on, as on Forge or Laravel Cloud. */
    private function serve(array $env): Process
    {
        $server = new Process(
            [PHP_BINARY, '-d', 'opcache.enable=1', '-d', 'opcache.memory_consumption=512', '-d', 'opcache.max_accelerated_files=100000', '-d', 'memory_limit=-1',
                '-S', '127.0.0.1:'.self::PORT, base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
            public_path(), $env + ['APP_ENV' => 'production', 'APP_DEBUG' => 'false'],
        );
        $server->start();
        foreach (range(1, 100) as $_) {
            if (@fsockopen('127.0.0.1', self::PORT)) {
                return $server;
            }
            usleep(50_000);
        }
        throw new \RuntimeException('The PHP server did not start: '.$server->getErrorOutput());
    }

    private function get(string $path): float
    {
        $t = hrtime(true);
        $response = Http::timeout(600)->get('http://127.0.0.1:'.self::PORT.$path);
        $ms = (hrtime(true) - $t) / 1e6;
        if (! $response->successful()) {
            throw new \RuntimeException("{$path} returned {$response->status()}");
        }

        return $ms;
    }

    private function median(array $values): float
    {
        sort($values);
        $n = count($values);

        return $n % 2 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    }

    private function ms(float $ms): string
    {
        return $ms >= 1000 ? number_format($ms / 1000, 2).' s' : number_format($ms, 0).' ms';
    }

    private function change(float $before, float $after, string $better = 'faster', string $worse = 'slower'): string
    {
        if ($before <= 0) {
            return '';
        }
        $ratio = $before / max($after, 0.001);
        if (abs($ratio - 1) < 0.05) {
            return 'about the same';
        }

        return $ratio > 1 ? sprintf('<fg=green>%.1f× %s</>', $ratio, $better) : sprintf('<fg=yellow>%.1f× %s</>', 1 / $ratio, $worse);
    }

    private function save(array $generated, array $result): void
    {
        File::ensureDirectoryExists($dir = storage_path('demo-results'));
        File::put("{$dir}/{$generated['shape']}-{$generated['size']}.json", json_encode(['generated' => $generated, 'php' => PHP_VERSION, 'measured_at' => date('c')] + $result, JSON_PRETTY_PRINT));
    }
}
