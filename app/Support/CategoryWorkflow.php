<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class CategoryWorkflow
{
    public const PROFILE_EDIT_PRINT = 'edit_print';

    public const PROFILE_PRINT_ONLY = 'print_only';

    public const PROFILE_DONE_ONLY = 'done_only';

    /** @deprecated Use PROFILE_DONE_ONLY */
    public const PROFILE_FRAMING_ONLY = self::PROFILE_DONE_ONLY;

    public static function normalize(?string $name): string
    {
        return mb_strtoupper(trim((string) $name));
    }

    /** @return list<string> */
    public static function normalizedNamesForProfile(string $profile): array
    {
        $key = match ($profile) {
            self::PROFILE_EDIT_PRINT => 'edit_print',
            self::PROFILE_PRINT_ONLY => 'print_only',
            self::PROFILE_DONE_ONLY => 'done_only',
            default => null,
        };
        if ($key === null) {
            return [];
        }

        $names = config('category_workflow.'.$key, []);
        if ($profile === self::PROFILE_DONE_ONLY) {
            $legacy = config('category_workflow.framing_only', []);
            $names = array_merge($names, is_array($legacy) ? $legacy : []);
        }

        return array_values(array_unique(array_map([self::class, 'normalize'], $names)));
    }

    public static function profileForCategoryName(?string $categoryName): string
    {
        $key = self::normalize($categoryName);
        if ($key === '') {
            return self::PROFILE_EDIT_PRINT;
        }

        foreach ([self::PROFILE_DONE_ONLY, self::PROFILE_PRINT_ONLY, self::PROFILE_EDIT_PRINT] as $profile) {
            if (in_array($key, self::normalizedNamesForProfile($profile), true)) {
                return $profile;
            }
        }

        return self::PROFILE_EDIT_PRINT;
    }

    public static function isDoneOnlyCategoryName(?string $categoryName): bool
    {
        return self::profileForCategoryName($categoryName) === self::PROFILE_DONE_ONLY;
    }

    /** @deprecated Use isDoneOnlyCategoryName() */
    public static function isFramingOnlyCategoryName(?string $categoryName): bool
    {
        return self::isDoneOnlyCategoryName($categoryName);
    }

    public static function isPrintOnlyCategoryName(?string $categoryName): bool
    {
        return self::profileForCategoryName($categoryName) === self::PROFILE_PRINT_ONLY;
    }

    public static function isEditPrintCategoryName(?string $categoryName): bool
    {
        return self::profileForCategoryName($categoryName) === self::PROFILE_EDIT_PRINT;
    }

    /**
     * @param  list<string>  $profiles
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function scopeProfileIn(EloquentBuilder|QueryBuilder $query, array $profiles, string $column = 'category_name'): void
    {
        $normalized = [];
        foreach ($profiles as $profile) {
            foreach (self::normalizedNamesForProfile($profile) as $name) {
                $normalized[$name] = true;
            }
        }
        $normalized = array_keys($normalized);

        if ($normalized === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function ($w) use ($normalized, $column): void {
            foreach ($normalized as $name) {
                $w->orWhereRaw(
                    'UPPER(TRIM(IFNULL('.$column.', ""))) = ?',
                    [$name]
                );
            }
        });
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function scopeDoneOnlyCategory(EloquentBuilder|QueryBuilder $query, string $column = 'category_name'): void
    {
        self::scopeProfileIn($query, [self::PROFILE_DONE_ONLY], $column);
    }

    /** @deprecated Use scopeDoneOnlyCategory() */
    public static function scopeFramingOnlyCategory(EloquentBuilder|QueryBuilder $query, string $column = 'category_name'): void
    {
        self::scopeDoneOnlyCategory($query, $column);
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function scopePrintOnlyCategory(EloquentBuilder|QueryBuilder $query, string $column = 'category_name'): void
    {
        self::scopeProfileIn($query, [self::PROFILE_PRINT_ONLY], $column);
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function scopeEditPrintCategory(EloquentBuilder|QueryBuilder $query, string $column = 'category_name'): void
    {
        self::scopeProfileIn($query, [self::PROFILE_EDIT_PRINT], $column);
    }

    /**
     * Lines that use the printer queue (edit then print, or print only).
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function scopePrintWorkflowCategory(EloquentBuilder|QueryBuilder $query, string $column = 'category_name'): void
    {
        self::scopeProfileIn($query, [self::PROFILE_EDIT_PRINT, self::PROFILE_PRINT_ONLY], $column);
    }

    /**
     * Exclude done-only categories (edit_print + print_only).
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function scopeNotDoneOnlyCategory(EloquentBuilder|QueryBuilder $query, string $column = 'category_name'): void
    {
        foreach (self::normalizedNamesForProfile(self::PROFILE_DONE_ONLY) as $name) {
            $query->whereRaw(
                'UPPER(TRIM(IFNULL('.$column.', ""))) <> ?',
                [$name]
            );
        }
    }

    public static function categoryMatchesUserJobPool(?string $categoryName, \App\Models\User $user): bool
    {
        $profile = self::profileForCategoryName($categoryName);

        if ($user->isEditorPrinterOnly() || $user->isEditorPrinterFraming()) {
            if (in_array($profile, [self::PROFILE_EDIT_PRINT, self::PROFILE_PRINT_ONLY], true)) {
                return true;
            }
            if ($user->isEditorPrinterFraming()) {
                return $profile === self::PROFILE_DONE_ONLY;
            }

            return false;
        }

        if ($user->jobPoolShowsPrintQueue() && $user->jobPoolShowsFramingQueue()) {
            return in_array($profile, [self::PROFILE_EDIT_PRINT, self::PROFILE_PRINT_ONLY, self::PROFILE_DONE_ONLY], true);
        }

        if ($user->jobPoolShowsPrintQueue()) {
            return in_array($profile, [self::PROFILE_EDIT_PRINT, self::PROFILE_PRINT_ONLY], true);
        }

        if ($user->jobPoolShowsFramingQueue()) {
            return $profile === self::PROFILE_DONE_ONLY;
        }

        return true;
    }

    /**
     * Job Pool SQL filter: printer queue, framing queue, or both (Printer + Framing role).
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function scopeJobPoolCategoriesForUser(EloquentBuilder|QueryBuilder $query, \App\Models\User $user, string $column = 'category_name'): void
    {
        if ($user->jobPoolShowsPrintQueue() && $user->jobPoolShowsFramingQueue()) {
            self::scopeProfileIn($query, [self::PROFILE_EDIT_PRINT, self::PROFILE_PRINT_ONLY, self::PROFILE_DONE_ONLY], $column);

            return;
        }

        if ($user->jobPoolShowsPrintQueue()) {
            self::scopePrintWorkflowCategory($query, $column);

            return;
        }

        if ($user->jobPoolShowsFramingQueue()) {
            self::scopeDoneOnlyCategory($query, $column);
        }
    }
}
