<?php

namespace App\Helpers;

use App\Models\Back\Catalog\Product\Product;

class Njuskalo
{
    /** Remove characters XML 1.0 forbids (control chars, etc.) */
    private function stripInvalidXml(string $s): string
    {
        return preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';
    }

    /** Escape for XML text nodes (NOT for CDATA) */
    private function x(string $s): string
    {
        $s = $this->stripInvalidXml($s);
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Build the human description; keep it plaintext or CDATA-safe */
    private function buildDescription(Product $p): string
    {
        $parts = [];

        if (!empty($p->description)) {
            // Convert basic HTML breaks to newlines for safer feeds
            $desc = preg_replace('/<br\s*\/?>/i', "\n\n", $p->description);
            $desc = strip_tags($desc); // or keep tags and use CDATA (see below)
            $parts[] = trim($desc);
        }
        if ($p->pages)      $parts[] = "Stranica: {$p->pages}";
        if ($p->dimensions) $parts[] = "Dimenzije: {$p->dimensions}";
        if ($p->origin)     $parts[] = "Jezik: {$p->origin}";
        if ($p->letter)     $parts[] = "Pismo: {$p->letter}";
        if ($p->condition)  $parts[] = "Stanje: {$p->condition}";
        if ($p->binding)    $parts[] = "Uvez: {$p->binding}";
        if ($p->year)       $parts[] = "Godina: {$p->year}";

        return $this->stripInvalidXml(implode("\n", $parts));
    }

    /** Create the XML feed */
    public function toXml(): string
    {
        $products = Product::query()
            ->where('status', 1)
            ->where('price', '!=', 0)
            ->where('quantity', '!=', 0)
            ->select('id','name','description','quantity','status','price','group','image','pages','dimensions','origin','slug','letter','condition','binding','year')
            ->get();

        $w = new \XMLWriter();
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('items');

        foreach ($products as $p) {
            $group = config('settings.njuskalo.sync.' . $p->group) ?? '';
            $imageUrl = asset($p->image);

            $w->startElement('item');

            $w->writeElement('id', (string)$p->id);
            $w->writeElement('name', $this->x((string)$p->name));
            $w->writeElement('slug', $this->x((string)$p->slug));
            $w->writeElement('group', $this->x((string)$group));
            $w->writeElement('price', number_format((float)$p->price, 2, '.', ''));
            $w->writeElement('image', $this->x((string)$imageUrl));

            // Description in CDATA to allow newlines or leftover markup safely
            $w->startElement('description');
            $w->writeCData($this->buildDescription($p));
            $w->endElement(); // description

            $w->endElement(); // item
        }

        $w->endElement();   // items
        $w->endDocument();

        return $w->outputMemory();
    }
}
