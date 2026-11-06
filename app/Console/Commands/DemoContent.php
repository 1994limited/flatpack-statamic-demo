<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes Dog-ear Books' content as plain files, the same every time for the same options.
 * Everything is made up. Two dials: how complex each entry is (shape) and how many there are (size).
 */
class DemoContent extends Command
{
    protected $signature = 'demo:content
        {--shape=builder : fixed (fields only), builder (a flat page builder) or nested (sections > columns > blocks > rich text with sets)}
        {--size=medium : small, medium, large or huge}';

    protected $description = 'Generate the demo bookshop\'s content';

    public const SHAPES = ['fixed', 'builder', 'nested'];

    /** Books in English per size. French and German translate some of them, and the journal grows with the shop. */
    public const SIZES = ['small' => 250, 'medium' => 1500, 'large' => 6000, 'huge' => 15000];

    private const ADJ = ['Quiet', 'Paper', 'Hidden', 'Northern', 'Last', 'Salt', 'Winter', 'Borrowed', 'Glass', 'Long', 'Small', 'Copper', 'Second', 'Distant', 'Golden', 'Restless', 'Folded', 'Lantern', 'Wild', 'Unwritten'];

    private const NOUN = ['Harbour', 'Orchard', 'Lighthouse', 'Library', 'Garden', 'River', 'Atlas', 'Season', 'Kingdom', 'Letters', 'Island', 'Station', 'Forest', 'Map', 'Archive', 'Weather', 'Bridge', 'Choir', 'Engine', 'Shore'];

    private const FIRST = ['Ada', 'Bram', 'Cleo', 'Dorian', 'Edie', 'Felix', 'Greta', 'Hal', 'Iris', 'Jonah', 'Kit', 'Lena', 'Milo', 'Nell', 'Otto', 'Pia', 'Rufus', 'Sana', 'Theo', 'Una', 'Viggo', 'Wren', 'Yara', 'Zeb'];

    private const LAST = ['Ashdown', 'Birch', 'Calloway', 'Dunmore', 'Ellery', 'Fairweather', 'Goss', 'Hartley', 'Inchbald', 'Juniper', 'Kettle', 'Lumley', 'Marsh', 'Nettleton', 'Oakes', 'Pennington', 'Quill', 'Rook', 'Sorrell', 'Thackeray', 'Underhill', 'Vane', 'Whitlock', 'Yarrow'];

    private const WORDS = ['the', 'shop', 'reader', 'quietly', 'chapter', 'river', 'paper', 'light', 'winter', 'story', 'kept', 'between', 'pages', 'harbour', 'small', 'slowly', 'remembered', 'letters', 'garden', 'and', 'of', 'a', 'with', 'old', 'map', 'evening', 'found', 'under', 'window', 'town', 'book', 'shelves', 'voice', 'season', 'travelled', 'across', 'bright', 'patient', 'the', 'of'];

    private const SUBJECTS = [
        'fiction' => ['Fiction', 'Romans', 'Romane'], 'crime' => ['Crime', 'Policier', 'Krimi'], 'poetry' => ['Poetry', 'Poésie', 'Lyrik'],
        'history' => ['History', 'Histoire', 'Geschichte'], 'travel' => ['Travel', 'Voyage', 'Reisen'], 'nature' => ['Nature', 'Nature', 'Natur'],
        'food' => ['Food and drink', 'Cuisine', 'Essen und Trinken'], 'children' => ['Children', 'Jeunesse', 'Kinderbuch'], 'science' => ['Science', 'Sciences', 'Wissenschaft'],
        'art' => ['Art', 'Art', 'Kunst'], 'music' => ['Music', 'Musique', 'Musik'], 'memoir' => ['Memoir', 'Mémoires', 'Erinnerungen'],
        'philosophy' => ['Philosophy', 'Philosophie', 'Philosophie'], 'sport' => ['Sport', 'Sport', 'Sport'], 'gardening' => ['Gardening', 'Jardinage', 'Garten'],
        'graphic-novels' => ['Graphic novels', 'Bandes dessinées', 'Comics'], 'plays' => ['Plays', 'Théâtre', 'Theater'], 'design' => ['Design', 'Design', 'Design'],
        'science-fiction' => ['Science fiction', 'Science-fiction', 'Science-Fiction'], 'local-interest' => ['Local interest', 'Région', 'Heimatkunde'],
    ];

    private string $shape;

    private string $c;

    private array $bookIds = [];

    public function handle(): int
    {
        $this->shape = (string) $this->option('shape');
        $size = (string) $this->option('size');
        if (! in_array($this->shape, self::SHAPES, true) || ! isset(self::SIZES[$size])) {
            $this->components->error('--shape is one of '.implode(', ', self::SHAPES).'; --size is one of '.implode(', ', array_keys(self::SIZES)).'.');

            return self::FAILURE;
        }

        mt_srand(1994);
        $this->c = base_path('content');
        $books = self::SIZES[$size];
        $t = microtime(true);

        foreach (['collections/books', 'collections/journal', 'collections/pages', 'taxonomies/authors', 'taxonomies/subjects', 'trees', 'globals/en', 'globals/fr', 'globals/de'] as $dir) {
            File::deleteDirectory("{$this->c}/{$dir}");
        }

        $authors = $this->authors(max(40, intdiv($books, 6)));
        $this->subjects();
        $this->books($books, $authors);
        $posts = $this->journal(max(20, intdiv($books, 10)));
        $this->pages();
        $this->globals();

        // The old content is gone, so are both caches of it: Flatpack's and Statamic's own.
        File::deleteDirectory(storage_path('statamic/flatpack'));
        \Statamic\Facades\Stache::clear();

        File::put(storage_path('demo.json'), json_encode(['shape' => $this->shape, 'size' => $size, 'books' => $books, 'generated_at' => date('c')]));

        $bytes = 0;
        foreach (File::allFiles($this->c) as $f) {
            $bytes += $f->getSize();
        }
        $this->components->info(sprintf(
            'Dog-ear Books, %s and %s: %s books (plus translations), %s journal posts, %s authors, %s MB of content in %.1f s.',
            $this->shape, $size, number_format($books), number_format($posts), number_format(count($authors)), number_format($bytes / 1048576, 1), microtime(true) - $t,
        ));
        $this->line('  Then: <comment>php please flatpack:build</comment> (or <comment>php please stache:warm</comment> with FLATPACK_ENABLED=false).');

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- content

    private function authors(int $n): array
    {
        $slugs = [];
        for ($i = 0; $i < $n; $i++) {
            $name = $this->pick(self::FIRST).' '.$this->pick(self::LAST);
            $slug = str($name)->slug().'-'.$i;
            $slugs[] = (string) $slug;
            $this->yaml("taxonomies/authors/{$slug}.yaml", ['title' => $name, 'bio' => $this->sentence(30)]);
        }

        return $slugs;
    }

    private function subjects(): void
    {
        foreach (self::SUBJECTS as $slug => [$en, $fr, $de]) {
            $this->yaml("taxonomies/subjects/{$slug}.yaml", [
                'title' => $en,
                'description' => $this->sentence(20),
                'localizations' => ['fr' => ['title' => $fr], 'de' => ['title' => $de]],
            ]);
        }
    }

    private function books(int $n, array $authors): void
    {
        $subjects = array_keys(self::SUBJECTS);
        for ($i = 0; $i < $n; $i++) {
            $id = $this->uuid("book-{$i}");
            $this->bookIds[] = $id;
            $title = $this->title();
            $slug = str($title)->slug().'-'.$i;
            $data = [
                'id' => $id,
                'blueprint' => 'book',
                'title' => $title,
                'summary' => $this->sentence(45),
                'authors' => array_values(array_unique([$authors[$i % count($authors)], ...($i % 7 === 0 ? [$authors[($i * 13) % count($authors)]] : [])])),
                'subjects' => array_values(array_unique([$subjects[$i % count($subjects)], $subjects[($i * 3 + 1) % count($subjects)]])),
                'isbn' => '978'.str_pad((string) (1000000000 + $i * 7919 % 999999999), 10, '0', STR_PAD_LEFT),
                'format' => ['hardback', 'paperback', 'audiobook'][$i % 3],
                'price' => round(6.99 + ($i % 23), 2),
                'year' => 1950 + ($i % 75),
            ];
            if ($i % 17 === 5) {
                $data['published'] = false;
            }
            $data += $this->body($i);
            $this->entry("collections/books/en/{$slug}.md", $data);

            foreach (['fr' => 3, 'de' => 6] as $site => $every) {
                if ($i % $every) {
                    continue;
                }
                $local = ['id' => $this->uuid("book-{$i}-{$site}"), 'origin' => $id, 'title' => $title." ({$site})"];
                if ($i % ($every * 2) === 0) {
                    $local += $this->body($i + 1);
                }
                $this->entry("collections/books/{$site}/{$slug}.md", $local);
            }
        }
    }

    private function journal(int $n): int
    {
        $subjects = array_keys(self::SUBJECTS);
        for ($i = 0; $i < $n; $i++) {
            $id = $this->uuid("post-{$i}");
            $date = date('Y-m-d', strtotime('2019-01-07') + $i * 86400 * 3);
            $title = $this->title(4);
            $slug = str($title)->slug().'-'.$i;
            $data = ['id' => $id, 'blueprint' => 'post', 'title' => $title, 'writer' => $this->pick(self::FIRST).' '.$this->pick(self::LAST), 'summary' => $this->sentence(30), 'subjects' => [$subjects[$i % count($subjects)]]];
            $data += $this->body($i);
            $this->entry("collections/journal/en/{$date}.{$slug}.md", $data);
            if ($i % 4 === 0) {
                $this->entry("collections/journal/fr/{$date}.{$slug}.md", ['id' => $this->uuid("post-{$i}-fr"), 'origin' => $id, 'title' => $title.' (fr)']);
            }
        }

        return $n;
    }

    private function pages(): void
    {
        $pages = [
            'home' => ['Dog-ear Books', 'home'],
            'books' => ['Books', 'books/index'],
            'journal' => ['Journal', 'journal/index'],
            'authors' => ['Authors', 'authors/index'],
            'subjects' => ['Subjects', 'subjects/index'],
            'about' => ['About the shop', 'page'],
            'visit' => ['Visit us', 'page'],
            'events' => ['Events', 'page'],
            'book-club' => ['Book club', 'page'],
            'gift-cards' => ['Gift cards', 'page'],
        ];
        $tree = [];
        foreach (['en', 'fr', 'de'] as $site) {
            $tree[$site] = [];
        }
        $n = 0;
        foreach ($pages as $slug => [$title, $template]) {
            $id = $this->uuid("page-{$slug}");
            $data = ['id' => $id, 'blueprint' => 'page', 'title' => $title, 'template' => $template, 'intro' => $this->sentence(25)] + ($template === 'page' ? $this->body($n++) : []);
            $this->entry("collections/pages/en/{$slug}.md", $data);
            $tree['en'][$slug] = $id;
            foreach (['fr' => 'Français', 'de' => 'Deutsch'] as $site => $label) {
                $lid = $this->uuid("page-{$slug}-{$site}");
                $this->entry("collections/pages/{$site}/{$slug}.md", ['id' => $lid, 'origin' => $id, 'title' => $title." ({$label})"]);
                $tree[$site][$slug] = $lid;
            }
        }
        foreach ($tree as $site => $ids) {
            // home at the root; book club and gift cards under "About"
            $items = [['entry' => $ids['home']]];
            foreach (['books', 'journal', 'authors', 'subjects', 'events', 'visit'] as $s) {
                $items[] = ['entry' => $ids[$s]];
            }
            $items[] = ['entry' => $ids['about'], 'children' => [['entry' => $ids['book-club']], ['entry' => $ids['gift-cards']]]];
            $this->yaml("trees/collections/{$site}/pages.yaml", ['tree' => $items]);
            $this->yaml("trees/navigation/{$site}/main.yaml", ['tree' => array_map(fn ($s) => ['id' => $this->uuid("nav-{$site}-{$s}"), 'entry' => $ids[$s]], ['books', 'journal', 'authors', 'subjects', 'events', 'visit', 'about'])]);
        }
    }

    private function globals(): void
    {
        $this->yaml('globals/en/shop.yaml', ['shop_name' => 'Dog-ear Books', 'address' => "14 Quill Lane\nNowhere-on-Sea", 'opening_hours' => [
            ['id' => 'h1', 'days' => 'Monday to Friday', 'hours' => '9am to 6pm'],
            ['id' => 'h2', 'days' => 'Saturday', 'hours' => '10am to 5pm'],
            ['id' => 'h3', 'days' => 'Sunday', 'hours' => 'Closed'],
        ]]);
        $this->yaml('globals/fr/shop.yaml', ['origin' => 'en', 'shop_name' => 'Dog-ear Books (Français)']);
        $this->yaml('globals/de/shop.yaml', ['origin' => 'en', 'shop_name' => 'Dog-ear Books (Deutsch)']);
    }

    // ---------------------------------------------------------------- the shapes

    /** The page builder for one entry, by shape. `fixed` has none: everything is in the fixed fields. */
    private function body(int $i): array
    {
        return match ($this->shape) {
            'fixed' => [],
            'builder' => ['page_builder' => array_map(fn ($k) => $this->block($i + $k), range(0, 3 + $i % 5))],
            'nested' => ['page_builder' => array_map(fn ($k) => $this->section($i + $k), range(0, 1 + $i % 3))],
        };
    }

    private function section(int $i): array
    {
        return ['id' => $this->rid(), 'type' => 'section', 'enabled' => true, 'heading' => $this->title(3), 'background' => ['plain', 'tinted', 'dark'][$i % 3], 'columns' => array_map(
            fn ($c) => ['id' => $this->rid(), 'type' => 'column', 'enabled' => true, 'width' => ['half', 'third', 'two_thirds'][($i + $c) % 3], 'blocks' => array_map(fn ($b) => $this->block($i + $c + $b, true), range(0, ($i + $c) % 3))],
            range(0, 1 + $i % 2),
        )];
    }

    private function block(int $i, bool $deep = false): array
    {
        return match ($i % 4) {
            0 => ['id' => $this->rid(), 'type' => 'text', 'enabled' => true, 'heading' => $this->title(4), 'text' => $this->bard($deep ? 2 : 3 + $i % 3, $deep || $this->shape === 'nested')],
            1 => ['id' => $this->rid(), 'type' => 'quote', 'enabled' => true, 'quote' => $this->sentence(28), 'cite' => $this->pick(self::FIRST).' '.$this->pick(self::LAST)],
            2 => ['id' => $this->rid(), 'type' => 'details', 'enabled' => true, 'rows' => array_map(fn ($r) => ['id' => $this->rid(), 'label' => $this->title(1), 'value' => $this->sentence(4)], range(0, 3))],
            3 => ['id' => $this->rid(), 'type' => 'shelf', 'enabled' => true, 'heading' => 'You might also like', 'books' => $this->someBooks($i)],
        };
    }

    /** Rich text as Bard stores it. With sets, a pull quote and a fact box sit inside the text. */
    private function bard(int $paragraphs, bool $sets): array
    {
        $out = [];
        for ($p = 0; $p < $paragraphs; $p++) {
            $out[] = ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => $this->sentence(45).' '],
                ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => $this->sentence(6)],
                ['type' => 'text', 'text' => ' '.$this->sentence(30)],
            ]];
            if ($sets && $p === 1) {
                $out[] = ['type' => 'set', 'attrs' => ['id' => $this->rid(), 'values' => ['type' => 'pullquote', 'quote' => $this->sentence(20)]]];
                $out[] = ['type' => 'set', 'attrs' => ['id' => $this->rid(), 'values' => ['type' => 'fact', 'rows' => array_map(fn () => ['id' => $this->rid(), 'label' => $this->title(1), 'value' => $this->sentence(5)], range(0, 2))]]];
            }
        }

        return $out;
    }

    private function someBooks(int $i): array
    {
        if (! $this->bookIds) {
            return [];
        }
        $n = count($this->bookIds);

        return array_values(array_unique(array_map(fn ($k) => $this->bookIds[($i * 31 + $k * 97) % $n], range(0, 3))));
    }

    // ---------------------------------------------------------------- helpers

    private function entry(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname("{$this->c}/{$path}"));
        file_put_contents("{$this->c}/{$path}", "---\n".Yaml::dump($data, 30, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)."---\n");
    }

    private function yaml(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname("{$this->c}/{$path}"));
        file_put_contents("{$this->c}/{$path}", Yaml::dump($data, 30, 2));
    }

    private function title(int $words = 3): string
    {
        $t = 'The '.$this->pick(self::ADJ).' '.$this->pick(self::NOUN);

        return $words > 3 ? $t.' of '.$this->pick(self::NOUN).'s' : ($words === 1 ? $this->pick(self::NOUN) : $t);
    }

    private function sentence(int $words): string
    {
        $out = [];
        for ($w = 0; $w < $words; $w++) {
            $out[] = $this->pick(self::WORDS);
        }

        return ucfirst(implode(' ', $out)).'.';
    }

    private function pick(array $list): string
    {
        return $list[mt_rand(0, count($list) - 1)];
    }

    private function rid(): string
    {
        return substr(md5((string) mt_rand()), 0, 8);
    }

    private function uuid(string $seed): string
    {
        $h = md5($seed);

        return substr($h, 0, 8).'-'.substr($h, 8, 4).'-4'.substr($h, 13, 3).'-8'.substr($h, 17, 3).'-'.substr($h, 20, 12);
    }
}
