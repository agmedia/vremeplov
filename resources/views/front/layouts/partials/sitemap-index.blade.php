<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($items as $item)
        <sitemap>
            <loc>{{ route('sitemap', ['sitemap' => $item]) }}</loc>
        </sitemap>
    @endforeach
    <sitemap>
        <loc>{{ route('sitemap.images') }}</loc>
    </sitemap>
</sitemapindex>
