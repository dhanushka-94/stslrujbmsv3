<?php

/**
 * One distinct color per POS category (case-insensitive match on category_name).
 * `names` lists POS spellings; `palette` keys into `palettes` below.
 */
return [
    'default_palette' => 'slate',

    'canonical' => [
        'album_package' => [
            'label' => 'Album Package',
            'palette' => 'rose',
            'names' => ['Album Package', 'ALBUM PACKAGE'],
        ],
        'digital_photo' => [
            'label' => 'Digital Photo',
            'palette' => 'violet',
            'names' => ['Digital Photo', 'DIGITAL PHOTO'],
        ],
        'digital_photo_small' => [
            'label' => 'Digital Photo Small Sizes',
            'palette' => 'purple',
            'names' => ['Digital Photo Small Size', 'Digital Photo Small Sizes', 'DIGITAL PHOTO SMALL SIZES'],
        ],
        'photo_collage' => [
            'label' => 'Photo Collage',
            'palette' => 'fuchsia',
            'names' => ['Photo Collage', 'PHOTO COLLAGE'],
        ],
        'studio_photo' => [
            'label' => 'Studio Photo',
            'palette' => 'indigo',
            'names' => ['Studio Photo', 'STUDIO PHOTO'],
        ],
        'studio_photo_small' => [
            'label' => 'Studio Photo Small Sizes',
            'palette' => 'blue',
            'names' => ['Studio Photo Small Size', 'Studio Photo Small Sizes', 'STUDIO PHOTO SMALL SIZES'],
        ],
        'studio_wedding' => [
            'label' => 'Studio Wedding',
            'palette' => 'pink',
            'names' => ['Studio Wedding', 'STUDIO WEDDING'],
        ],
        'sublimation_print' => [
            'label' => 'Sublimation Print',
            'palette' => 'orange',
            'names' => ['Sublimation Print', 'SUBLIMATION PRINT'],
        ],
        'wall_clocks' => [
            'label' => 'Wall Clocks',
            'palette' => 'amber',
            'names' => ['Wall Clocks'],
        ],
        'media_print' => [
            'label' => 'Media Print',
            'palette' => 'sky',
            'names' => ['Media Print', 'MEDIA PRINT'],
        ],
        'media_print_small' => [
            'label' => 'Media Print Small Sizes',
            'palette' => 'cyan',
            'names' => ['Media Print Small Size', 'Media Print Small Sizes', 'MEDIA PRINT SMALL SIZES'],
        ],
        'crystal_frame' => [
            'label' => 'Crystal Frame',
            'palette' => 'emerald',
            'names' => ['Crystal Frame', 'CRYSTAL FRAME', 'Crysal Frame'],
        ],
        'engraving' => [
            'label' => 'Engraving',
            'palette' => 'lime',
            'names' => ['Engraving'],
        ],
        'frame' => [
            'label' => 'Frame',
            'palette' => 'teal',
            'names' => ['Frame', 'FRAME'],
        ],
        'glass' => [
            'label' => 'Glass',
            'palette' => 'green',
            'names' => ['Glass', 'GLASS'],
        ],
        'laminating' => [
            'label' => 'Laminating',
            'palette' => 'mint',
            'names' => ['Laminating', 'LAMINATING'],
        ],
        'ply_mount' => [
            'label' => 'Ply Mount and Others',
            'palette' => 'stone',
            'names' => ['Ply Mount and Others', 'Ply Mount and others', 'PLY MOUNT AND OTHERS'],
        ],
        'sale_product' => [
            'label' => 'Sale Product',
            'palette' => 'zinc',
            'names' => ['Sale Product', 'SALE PRODUCT'],
        ],
        'delivery' => [
            'label' => 'Delivery',
            'palette' => 'yellow',
            'names' => ['Delivery', 'DELIVERY'],
        ],
        'old_bill_balance' => [
            'label' => 'Old Bill Balance',
            'palette' => 'neutral',
            'names' => ['Old Bill Balance', 'OLD BILL BALANCE'],
        ],
        'others' => [
            'label' => 'Others',
            'palette' => 'gray',
            'names' => ['Others', 'OTHERS'],
        ],
        'promotion' => [
            'label' => 'Promotion',
            'palette' => 'red',
            'names' => ['Promotion', 'Promotion '],
        ],
        'z_office_use' => [
            'label' => 'Z Office use',
            'palette' => 'slate',
            'names' => ['Z Office use', 'Z Office Use'],
        ],
    ],

    'palettes' => [
        'rose' => [
            'row' => 'border-l-[3px] border-l-rose-500 bg-rose-50/55 dark:border-l-rose-400 dark:bg-rose-950/25',
            'badge' => 'bg-rose-100 text-rose-900 ring-1 ring-inset ring-rose-200/80 dark:bg-rose-900/40 dark:text-rose-100 dark:ring-rose-700/50',
            'pill' => 'bg-rose-100/90 text-rose-900 ring-1 ring-rose-200/70 dark:bg-rose-900/35 dark:text-rose-100 dark:ring-rose-800/60',
        ],
        'pink' => [
            'row' => 'border-l-[3px] border-l-pink-500 bg-pink-50/55 dark:border-l-pink-400 dark:bg-pink-950/25',
            'badge' => 'bg-pink-100 text-pink-900 ring-1 ring-inset ring-pink-200/80 dark:bg-pink-900/40 dark:text-pink-100 dark:ring-pink-700/50',
            'pill' => 'bg-pink-100/90 text-pink-900 ring-1 ring-pink-200/70 dark:bg-pink-900/35 dark:text-pink-100 dark:ring-pink-800/60',
        ],
        'fuchsia' => [
            'row' => 'border-l-[3px] border-l-fuchsia-500 bg-fuchsia-50/55 dark:border-l-fuchsia-400 dark:bg-fuchsia-950/25',
            'badge' => 'bg-fuchsia-100 text-fuchsia-900 ring-1 ring-inset ring-fuchsia-200/80 dark:bg-fuchsia-900/40 dark:text-fuchsia-100 dark:ring-fuchsia-700/50',
            'pill' => 'bg-fuchsia-100/90 text-fuchsia-900 ring-1 ring-fuchsia-200/70 dark:bg-fuchsia-900/35 dark:text-fuchsia-100 dark:ring-fuchsia-800/60',
        ],
        'purple' => [
            'row' => 'border-l-[3px] border-l-purple-500 bg-purple-50/55 dark:border-l-purple-400 dark:bg-purple-950/25',
            'badge' => 'bg-purple-100 text-purple-900 ring-1 ring-inset ring-purple-200/80 dark:bg-purple-900/40 dark:text-purple-100 dark:ring-purple-700/50',
            'pill' => 'bg-purple-100/90 text-purple-900 ring-1 ring-purple-200/70 dark:bg-purple-900/35 dark:text-purple-100 dark:ring-purple-800/60',
        ],
        'violet' => [
            'row' => 'border-l-[3px] border-l-violet-500 bg-violet-50/55 dark:border-l-violet-400 dark:bg-violet-950/25',
            'badge' => 'bg-violet-100 text-violet-900 ring-1 ring-inset ring-violet-200/80 dark:bg-violet-900/40 dark:text-violet-100 dark:ring-violet-700/50',
            'pill' => 'bg-violet-100/90 text-violet-900 ring-1 ring-violet-200/70 dark:bg-violet-900/35 dark:text-violet-100 dark:ring-violet-800/60',
        ],
        'indigo' => [
            'row' => 'border-l-[3px] border-l-indigo-500 bg-indigo-50/55 dark:border-l-indigo-400 dark:bg-indigo-950/25',
            'badge' => 'bg-indigo-100 text-indigo-900 ring-1 ring-inset ring-indigo-200/80 dark:bg-indigo-900/40 dark:text-indigo-100 dark:ring-indigo-700/50',
            'pill' => 'bg-indigo-100/90 text-indigo-900 ring-1 ring-indigo-200/70 dark:bg-indigo-900/35 dark:text-indigo-100 dark:ring-indigo-800/60',
        ],
        'blue' => [
            'row' => 'border-l-[3px] border-l-blue-500 bg-blue-50/55 dark:border-l-blue-400 dark:bg-blue-950/25',
            'badge' => 'bg-blue-100 text-blue-900 ring-1 ring-inset ring-blue-200/80 dark:bg-blue-900/40 dark:text-blue-100 dark:ring-blue-700/50',
            'pill' => 'bg-blue-100/90 text-blue-900 ring-1 ring-blue-200/70 dark:bg-blue-900/35 dark:text-blue-100 dark:ring-blue-800/60',
        ],
        'sky' => [
            'row' => 'border-l-[3px] border-l-sky-500 bg-sky-50/55 dark:border-l-sky-400 dark:bg-sky-950/25',
            'badge' => 'bg-sky-100 text-sky-900 ring-1 ring-inset ring-sky-200/80 dark:bg-sky-900/40 dark:text-sky-100 dark:ring-sky-700/50',
            'pill' => 'bg-sky-100/90 text-sky-900 ring-1 ring-sky-200/70 dark:bg-sky-900/35 dark:text-sky-100 dark:ring-sky-800/60',
        ],
        'cyan' => [
            'row' => 'border-l-[3px] border-l-cyan-500 bg-cyan-50/55 dark:border-l-cyan-400 dark:bg-cyan-950/25',
            'badge' => 'bg-cyan-100 text-cyan-900 ring-1 ring-inset ring-cyan-200/80 dark:bg-cyan-900/40 dark:text-cyan-100 dark:ring-cyan-700/50',
            'pill' => 'bg-cyan-100/90 text-cyan-900 ring-1 ring-cyan-200/70 dark:bg-cyan-900/35 dark:text-cyan-100 dark:ring-cyan-800/60',
        ],
        'teal' => [
            'row' => 'border-l-[3px] border-l-teal-500 bg-teal-50/55 dark:border-l-teal-400 dark:bg-teal-950/25',
            'badge' => 'bg-teal-100 text-teal-900 ring-1 ring-inset ring-teal-200/80 dark:bg-teal-900/40 dark:text-teal-100 dark:ring-teal-700/50',
            'pill' => 'bg-teal-100/90 text-teal-900 ring-1 ring-teal-200/70 dark:bg-teal-900/35 dark:text-teal-100 dark:ring-teal-800/60',
        ],
        'emerald' => [
            'row' => 'border-l-[3px] border-l-emerald-500 bg-emerald-50/55 dark:border-l-emerald-400 dark:bg-emerald-950/25',
            'badge' => 'bg-emerald-100 text-emerald-900 ring-1 ring-inset ring-emerald-200/80 dark:bg-emerald-900/40 dark:text-emerald-100 dark:ring-emerald-700/50',
            'pill' => 'bg-emerald-100/90 text-emerald-900 ring-1 ring-emerald-200/70 dark:bg-emerald-900/35 dark:text-emerald-100 dark:ring-emerald-800/60',
        ],
        'green' => [
            'row' => 'border-l-[3px] border-l-green-500 bg-green-50/55 dark:border-l-green-400 dark:bg-green-950/25',
            'badge' => 'bg-green-100 text-green-900 ring-1 ring-inset ring-green-200/80 dark:bg-green-900/40 dark:text-green-100 dark:ring-green-700/50',
            'pill' => 'bg-green-100/90 text-green-900 ring-1 ring-green-200/70 dark:bg-green-900/35 dark:text-green-100 dark:ring-green-800/60',
        ],
        'lime' => [
            'row' => 'border-l-[3px] border-l-lime-500 bg-lime-50/55 dark:border-l-lime-400 dark:bg-lime-950/25',
            'badge' => 'bg-lime-100 text-lime-900 ring-1 ring-inset ring-lime-200/80 dark:bg-lime-900/40 dark:text-lime-100 dark:ring-lime-700/50',
            'pill' => 'bg-lime-100/90 text-lime-900 ring-1 ring-lime-200/70 dark:bg-lime-900/35 dark:text-lime-100 dark:ring-lime-800/60',
        ],
        'mint' => [
            'row' => 'border-l-[3px] border-l-emerald-400 bg-emerald-50/40 dark:border-l-emerald-300 dark:bg-emerald-950/20',
            'badge' => 'bg-emerald-50 text-emerald-950 ring-1 ring-inset ring-emerald-300/80 dark:bg-emerald-900/30 dark:text-emerald-50 dark:ring-emerald-600/50',
            'pill' => 'bg-emerald-50/90 text-emerald-950 ring-1 ring-emerald-300/70 dark:bg-emerald-900/25 dark:text-emerald-50 dark:ring-emerald-700/60',
        ],
        'yellow' => [
            'row' => 'border-l-[3px] border-l-yellow-500 bg-yellow-50/55 dark:border-l-yellow-400 dark:bg-yellow-950/25',
            'badge' => 'bg-yellow-100 text-yellow-900 ring-1 ring-inset ring-yellow-200/80 dark:bg-yellow-900/40 dark:text-yellow-100 dark:ring-yellow-700/50',
            'pill' => 'bg-yellow-100/90 text-yellow-900 ring-1 ring-yellow-200/70 dark:bg-yellow-900/35 dark:text-yellow-100 dark:ring-yellow-800/60',
        ],
        'amber' => [
            'row' => 'border-l-[3px] border-l-amber-500 bg-amber-50/55 dark:border-l-amber-400 dark:bg-amber-950/25',
            'badge' => 'bg-amber-100 text-amber-900 ring-1 ring-inset ring-amber-200/80 dark:bg-amber-900/40 dark:text-amber-100 dark:ring-amber-700/50',
            'pill' => 'bg-amber-100/90 text-amber-900 ring-1 ring-amber-200/70 dark:bg-amber-900/35 dark:text-amber-100 dark:ring-amber-800/60',
        ],
        'orange' => [
            'row' => 'border-l-[3px] border-l-orange-500 bg-orange-50/55 dark:border-l-orange-400 dark:bg-orange-950/25',
            'badge' => 'bg-orange-100 text-orange-900 ring-1 ring-inset ring-orange-200/80 dark:bg-orange-900/40 dark:text-orange-100 dark:ring-orange-700/50',
            'pill' => 'bg-orange-100/90 text-orange-900 ring-1 ring-orange-200/70 dark:bg-orange-900/35 dark:text-orange-100 dark:ring-orange-800/60',
        ],
        'red' => [
            'row' => 'border-l-[3px] border-l-red-500 bg-red-50/55 dark:border-l-red-400 dark:bg-red-950/25',
            'badge' => 'bg-red-100 text-red-900 ring-1 ring-inset ring-red-200/80 dark:bg-red-900/40 dark:text-red-100 dark:ring-red-700/50',
            'pill' => 'bg-red-100/90 text-red-900 ring-1 ring-red-200/70 dark:bg-red-900/35 dark:text-red-100 dark:ring-red-800/60',
        ],
        'stone' => [
            'row' => 'border-l-[3px] border-l-stone-500 bg-stone-50/55 dark:border-l-stone-400 dark:bg-stone-950/25',
            'badge' => 'bg-stone-100 text-stone-900 ring-1 ring-inset ring-stone-200/80 dark:bg-stone-900/40 dark:text-stone-100 dark:ring-stone-700/50',
            'pill' => 'bg-stone-100/90 text-stone-900 ring-1 ring-stone-200/70 dark:bg-stone-900/35 dark:text-stone-100 dark:ring-stone-800/60',
        ],
        'neutral' => [
            'row' => 'border-l-[3px] border-l-neutral-500 bg-neutral-50/55 dark:border-l-neutral-400 dark:bg-neutral-950/25',
            'badge' => 'bg-neutral-100 text-neutral-900 ring-1 ring-inset ring-neutral-200/80 dark:bg-neutral-900/40 dark:text-neutral-100 dark:ring-neutral-700/50',
            'pill' => 'bg-neutral-100/90 text-neutral-900 ring-1 ring-neutral-200/70 dark:bg-neutral-900/35 dark:text-neutral-100 dark:ring-neutral-800/60',
        ],
        'gray' => [
            'row' => 'border-l-[3px] border-l-gray-500 bg-gray-50/55 dark:border-l-gray-400 dark:bg-gray-950/25',
            'badge' => 'bg-gray-100 text-gray-900 ring-1 ring-inset ring-gray-200/80 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-700/50',
            'pill' => 'bg-gray-100/90 text-gray-900 ring-1 ring-gray-200/70 dark:bg-gray-900/35 dark:text-gray-100 dark:ring-gray-800/60',
        ],
        'zinc' => [
            'row' => 'border-l-[3px] border-l-zinc-500 bg-zinc-50/55 dark:border-l-zinc-400 dark:bg-zinc-950/25',
            'badge' => 'bg-zinc-100 text-zinc-900 ring-1 ring-inset ring-zinc-200/80 dark:bg-zinc-900/40 dark:text-zinc-100 dark:ring-zinc-700/50',
            'pill' => 'bg-zinc-100/90 text-zinc-900 ring-1 ring-zinc-200/70 dark:bg-zinc-900/35 dark:text-zinc-100 dark:ring-zinc-800/60',
        ],
        'slate' => [
            'row' => 'border-l-[3px] border-l-slate-500 bg-slate-50/55 dark:border-l-slate-400 dark:bg-slate-950/25',
            'badge' => 'bg-slate-100 text-slate-900 ring-1 ring-inset ring-slate-200/80 dark:bg-slate-900/40 dark:text-slate-100 dark:ring-slate-700/50',
            'pill' => 'bg-slate-100/90 text-slate-900 ring-1 ring-slate-200/70 dark:bg-slate-900/35 dark:text-slate-100 dark:ring-slate-800/60',
        ],
    ],
];
