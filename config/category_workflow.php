<?php

/**
 * POS category workflow profiles (matched case-insensitively on job_edits.category_name).
 *
 * edit_print: Editing → Print (Printed). No separate Done step.
 * print_only: Print only (skip editing). Printed completes the line.
 * done_only: Single Done step (skip editing and print).
 *
 * Include POS spellings (e.g. SMALL SIZES) so names match sma_categories.name on products.
 */
return [
    'edit_print' => [
        'Album Package',
        'ALBUM PACKAGE',
        'Digital Photo',
        'DIGITAL PHOTO',
        'Digital Photo Small Size',
        'Digital Photo Small Sizes',
        'DIGITAL PHOTO SMALL SIZES',
        'Photo Collage',
        'PHOTO COLLAGE',
        'Studio Photo',
        'STUDIO PHOTO',
        'Studio Photo Small Size',
        'Studio Photo Small Sizes',
        'STUDIO PHOTO SMALL SIZES',
        'Studio Wedding',
        'STUDIO WEDDING',
        'Sublimation Print',
        'SUBLIMATION PRINT',
        'Wall Clocks',
    ],

    'print_only' => [
        'Media Print',
        'MEDIA PRINT',
        'Media Print Small Size',
        'Media Print Small Sizes',
        'MEDIA PRINT SMALL SIZES',
    ],

    'done_only' => [
        'Crystal Frame',
        'CRYSTAL FRAME',
        'Crysal Frame',
        'Engraving',
        'Frame',
        'FRAME',
        'Glass',
        'GLASS',
        'Laminating',
        'LAMINATING',
        'Ply Mount and Others',
        'Ply Mount and others',
        'PLY MOUNT AND OTHERS',
        'Sale Product',
        'SALE PRODUCT',
        'Delivery',
        'DELIVERY',
        'Old Bill Balance',
        'OLD BILL BALANCE',
        'Others',
        'OTHERS',
        'Promotion',
        'Promotion ',
        'Z Office use',
        'Z Office Use',
    ],
];
