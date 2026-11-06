# Dog-ear Books: a Flatpack demo

A made-up bookshop for trying [Flatpack for Statamic](https://github.com/1994limited/flatpack-statamic) on a site big enough to show what it does: books in three languages, a journal, authors, subjects, structured pages, a nav and globals. Everything in it is generated, from the books to the authors, so you can make it as small or as large, and as simple or as complex, as you like, then measure the same site with and without Flatpack.

## Running it

Flatpack is installed from [its GitHub repository](https://github.com/1994limited/flatpack-statamic) until it's on Packagist.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan demo:users
php artisan demo:content
php please flatpack:build
php artisan serve
```

Sign in at `/cp` as `admin@example.com` with the password `password`. `demo:users` refuses to run unless `APP_ENV=local`, since its password is known.

## Two dials: shape and size

`demo:content` writes the shop's content as plain Markdown and YAML files, the same every time for the same options.

```bash
php artisan demo:content --shape=nested --size=large
```

**Shape** is how complex each entry is:

| Shape | Each book, journal post and page has |
|---|---|
| `fixed` | Fixed fields only: title, summary, authors, subjects, ISBN, format, price, year. No page builder. |
| `builder` | The fixed fields, plus a page builder of 4 to 8 blocks: text (Bard), quotes, detail tables and shelves of related books. |
| `nested` | The fixed fields, plus a deeply nested page builder: sections, holding columns, holding blocks, with Bard text that holds its own sets (pull quotes, fact boxes). |

**Size** is how many there are:

| Size | Books (English) | With French and German translations | Journal posts | Authors |
|---|---|---|---|---|
| `small` | 250 | about 375 | 25 | 41 |
| `medium` | 1,500 | about 2,250 | 150 | 250 |
| `large` | 6,000 | about 9,000 | 600 | 1,000 |
| `huge` | 15,000 | about 22,500 | 1,500 | 2,500 |

Content on disk ranges from under 1 MB (`fixed`, `small`) to a few hundred MB (`nested`, `huge`). After generating, build again: `php please flatpack:build`.

## Comparing with and without Flatpack

```bash
php artisan demo:compare                        # the content you generated last
php artisan demo:compare --shape=nested --size=large
php artisan demo:compare --all                  # every shape at small, medium and large
```

It measures the same site twice, once on Statamic's own Stache and once with Flatpack, with each step in a fresh PHP process and pages fetched from a real PHP server with OPcache on (as on Forge or Laravel Cloud):

| Measured | What it is |
|---|---|
| Build at deploy | `stache:clear` + `stache:warm` against `flatpack:build`, and the memory each needs |
| First request after a deploy | A book page from a freshly started server, so nothing is in memory yet |
| Page loads | Home, a page of the book list, a book, a journal post, an author and a subject (median of several requests, after warming up) |
| Saving an entry | Editing and saving a book, as the Control Panel does |

Results are also saved as JSON in `storage/demo-results/`.

What to expect: Flatpack makes getting content out of Statamic faster and lighter, so builds, saves and memory improve most, and more so as the site grows and its page builders get deeper. A page's own rendering (Antlers, Blade) isn't something Flatpack changes, so page loads improve less, and least on pages that do a lot of rendering.

To browse the site on Statamic's own Stache instead, set `FLATPACK_ENABLED=false` in `.env` and run `php please stache:warm`.

### On one MacBook

Statamic → Flatpack, measured with `php artisan demo:compare --all` on an Apple M1 Pro with PHP 8.5.12, Statamic 6.40. "Typical page" is the median of the six pages. Your numbers will differ: run it on your own machine.

| Shape / size | Build | Build memory | First request | Typical page | Save |
|---|---|---|---|---|---|
| fixed / small | 946 ms → 784 ms | 78.5 → 74.5 MB | 384 ms → 390 ms | 70 ms → 63 ms | 110 ms → 91 ms |
| fixed / medium | 4.96 s → 4.01 s | 142.5 → 134.5 MB | 430 ms → 434 ms | 122 ms → 104 ms | 656 ms → 205 ms |
| fixed / large | 22.08 s → 17.25 s | 376.5 → 307 MB | 602 ms → 655 ms | 316 ms → 259 ms | 1.52 s → 665 ms |
| builder / small | 1.53 s → 894 ms | 132.5 → 72.5 MB | 421 ms → 413 ms | 82 ms → 72 ms | 267 ms → 95 ms |
| builder / medium | 9.03 s → 4.85 s | 348.5 → 142.5 MB | 515 ms → 538 ms | 152 ms → 132 ms | 283 ms → 214 ms |
| builder / large | 37.25 s → 19.53 s | 1172.5 → 363 MB | 774 ms → 794 ms | 359 ms → 301 ms | 1.73 s → 701 ms |
| nested / small | 2.57 s → 919 ms | 162.5 → 72.5 MB | 457 ms → 455 ms | 100 ms → 87 ms | 110 ms → 105 ms |
| nested / medium | 14.55 s → 4.91 s | 632.5 → 142.5 MB | 525 ms → 540 ms | 194 ms → 181 ms | 268 ms → 215 ms |
| nested / large | 67.27 s → 20.49 s | 2246.5 → 363 MB | 1.31 s → 1.22 s | 563 ms → 488 ms | 2.14 s → 674 ms |

Reading it: the deeper the page builders and the bigger the site, the more Flatpack saves at build time (3.3× faster and 6× less memory for `nested` / `large`). Page loads improve by 7 to 20%, because most of a page's time here is rendering, which Flatpack doesn't change. The first request after a deploy is about the same, because a freshly started PHP server's time goes on compiling the framework, not on content. Saves are noisy single measurements; treat them as a direction, not a figure.

## Licence

The demo's own code (templates, styles, content generator and scripts) is MIT licensed: see [LICENSE.md](LICENSE.md). Flatpack itself is commercial software with its own licence.
