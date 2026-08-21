<?php

namespace App\Support;

use App\Models\JobEdit;

/**
 * Per-category Tailwind visuals (see config/category_colors.php).
 */
class CategoryAppearance
{
    /** @var array<string, string>|null normalized name => canonical key */
    private static ?array $nameIndex = null;

    public static function normalize(?string $name): string
    {
        return CategoryWorkflow::normalize($name);
    }

    /** @return array{label: string, short: string, row: string, badge: string, pill: string} */
    public static function tokensForCategoryName(?string $categoryName): array
    {
        $canonical = self::resolveCanonicalKey($categoryName);
        $config = $canonical !== null
            ? (config('category_colors.canonical.'.$canonical) ?? [])
            : [];

        $paletteKey = is_array($config) && isset($config['palette'])
            ? (string) $config['palette']
            : (string) config('category_colors.default_palette', 'slate');

        $palette = self::paletteTokens($paletteKey);
        $profile = CategoryWorkflow::profileForCategoryName($categoryName);

        return array_merge($palette, [
            'label' => is_array($config) && isset($config['label'])
                ? (string) $config['label']
                : (filled($categoryName) ? trim((string) $categoryName) : 'Unknown category'),
            'short' => self::workflowShortLabel($profile),
        ]);
    }

    /** @return array{label: string, short: string, row: string, badge: string, pill: string} */
    public static function tokensForJobEdit(JobEdit $edit): array
    {
        return self::tokensForCategoryName($edit->category_name);
    }

    /** @deprecated Use tokensForCategoryName(); kept for callers passing workflow profile. */
    public static function tokensForProfile(string $profile): array
    {
        $sample = match ($profile) {
            CategoryWorkflow::PROFILE_PRINT_ONLY => 'Media Print',
            CategoryWorkflow::PROFILE_DONE_ONLY => 'Frame',
            default => 'Studio Photo',
        };

        return self::tokensForCategoryName($sample);
    }

    public static function profileForCategoryName(?string $categoryName): string
    {
        return CategoryWorkflow::profileForCategoryName($categoryName);
    }

    public static function profileForJobEdit(JobEdit $edit): string
    {
        return CategoryWorkflow::profileForCategoryName($edit->category_name);
    }

    /** @return list<array{key: string, label: string, badge: string}> */
    public static function legendEntries(): array
    {
        $canonical = config('category_colors.canonical', []);
        if (! is_array($canonical)) {
            return [];
        }

        $entries = [];
        foreach ($canonical as $key => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            $label = (string) ($meta['label'] ?? $key);
            $paletteKey = (string) ($meta['palette'] ?? config('category_colors.default_palette', 'slate'));
            $entries[] = [
                'key' => (string) $key,
                'label' => $label,
                'badge' => self::paletteTokens($paletteKey)['badge'],
            ];
        }

        usort($entries, fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $entries;
    }

    public static function isValidCanonicalKey(?string $key): bool
    {
        if ($key === null || $key === '') {
            return false;
        }
        $canonical = config('category_colors.canonical', []);

        return is_array($canonical) && isset($canonical[$key]) && is_array($canonical[$key]);
    }

    /** @return list<string> POS / stored category_name spellings for a canonical key */
    public static function namesForCanonicalKey(string $key): array
    {
        if (! self::isValidCanonicalKey($key)) {
            return [];
        }
        $meta = config('category_colors.canonical.'.$key, []);
        $names = is_array($meta) ? ($meta['names'] ?? []) : [];
        if (! is_array($names)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($n) => trim((string) $n),
            $names
        ), fn ($n) => $n !== '')));
    }

    /** @return list<string> Uppercased names for DB matching */
    public static function normalizedNamesForCanonicalKey(string $key): array
    {
        return array_values(array_unique(array_map(
            [self::class, 'normalize'],
            self::namesForCanonicalKey($key)
        )));
    }

    public static function labelForCanonicalKey(?string $key): ?string
    {
        if (! self::isValidCanonicalKey($key)) {
            return null;
        }
        $meta = config('category_colors.canonical.'.$key, []);

        return is_array($meta) && isset($meta['label'])
            ? (string) $meta['label']
            : (string) $key;
    }

    private static function workflowShortLabel(string $profile): string
    {
        return match ($profile) {
            CategoryWorkflow::PROFILE_PRINT_ONLY => 'Print',
            CategoryWorkflow::PROFILE_DONE_ONLY => 'Done',
            default => 'Edit',
        };
    }

    private static function resolveCanonicalKey(?string $categoryName): ?string
    {
        $normalized = self::normalize($categoryName);
        if ($normalized === '') {
            return null;
        }

        if (self::$nameIndex === null) {
            self::$nameIndex = [];
            $canonical = config('category_colors.canonical', []);
            if (is_array($canonical)) {
                foreach ($canonical as $key => $meta) {
                    if (! is_array($meta)) {
                        continue;
                    }
                    foreach ($meta['names'] ?? [] as $name) {
                        self::$nameIndex[self::normalize($name)] = (string) $key;
                    }
                }
            }
        }

        return self::$nameIndex[$normalized] ?? null;
    }

    /** @return array{row: string, badge: string, pill: string} */
    private static function paletteTokens(string $paletteKey): array
    {
        $palettes = config('category_colors.palettes', []);
        $fallback = (string) config('category_colors.default_palette', 'slate');
        $set = is_array($palettes) && isset($palettes[$paletteKey]) && is_array($palettes[$paletteKey])
            ? $palettes[$paletteKey]
            : (is_array($palettes) && isset($palettes[$fallback]) && is_array($palettes[$fallback])
                ? $palettes[$fallback]
                : []);

        return [
            'row' => (string) ($set['row'] ?? 'border-l-[3px] border-l-slate-500 bg-slate-50/55 dark:border-l-slate-400 dark:bg-slate-950/25'),
            'badge' => (string) ($set['badge'] ?? 'bg-slate-100 text-slate-900 ring-1 ring-inset ring-slate-200/80 dark:bg-slate-900/40 dark:text-slate-100 dark:ring-slate-700/50'),
            'pill' => (string) ($set['pill'] ?? 'bg-slate-100/90 text-slate-900 ring-1 ring-slate-200/70 dark:bg-slate-900/35 dark:text-slate-100 dark:ring-slate-800/60'),
        ];
    }
}
