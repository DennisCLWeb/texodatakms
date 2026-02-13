<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Blueprint;

class ImportFaqFromHtml extends Command
{
    protected $signature = 'faq:import {file : Pad naar de faq.html file}';
    protected $description = 'Importeer FAQ-items uit een HTML-bestand naar de Statamic FAQ-collection';

    private array $badgeMap = [
        'badge-all' => 'all',
        'badge-distributor' => 'distributor',
        'badge-manager' => 'manager',
        'badge-wearer' => 'wearer',
    ];

    private array $categoryMap = [
        'Algemeen' => 'algemeen',
        'Webshops & Productbeheer' => 'webshops',
        'Klantbeheer & Organisatie' => 'klantbeheer',
        'Bestellen & Betalen' => 'bestellen',
        'Orderverwerking & Facturatie' => 'orderverwerking',
        'Puntensysteem & Budgetbeheer' => 'puntensysteem',
        'Integraties & Koppelingen' => 'integraties',
        'Rollen & Toegang' => 'rollen',
        'BTW & Financieel' => 'btw',
        'Modificaties & Maatwerk' => 'modificaties',
        'Waarom Texodata KMS inzetten voor uw klanten?' => 'waarom',
    ];

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("Bestand niet gevonden: {$file}");
            return self::FAILURE;
        }

        // Step 1: Ensure collection exists
        $this->ensureCollectionExists();

        // Step 2: Ensure blueprint exists
        $this->ensureBlueprintExists();

        // Step 3: Parse HTML
        $entries = $this->parseHtml(file_get_contents($file));
        $this->info("Gevonden: " . count($entries) . " FAQ-items");

        // Step 4: Import entries
        $bar = $this->output->createProgressBar(count($entries));
        $bar->start();

        $created = 0;
        foreach ($entries as $index => $data) {
            $slug = $this->generateSlug($data['question']);

            // Skip if entry with this slug already exists
            $existing = Entry::query()
                ->where('collection', 'faq')
                ->where('slug', $slug)
                ->first();

            if ($existing) {
                $bar->advance();
                continue;
            }

            $entry = Entry::make()
                ->collection('faq')
                ->slug($slug)
                ->data([
                    'title' => $data['question'],
                    'question' => $data['question'],
                    'answer' => $this->htmlToBardContent($data['answer']),
                    'section_title' => $data['section_title'],
                    'category' => $data['category'],
                    'badge' => $data['badge'],
                    'sort_order' => $index + 1,
                ]);

            $entry->save();
            $created++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Klaar! {$created} nieuwe FAQ-items aangemaakt.");

        if ($created < count($entries)) {
            $this->warn(count($entries) - $created . " items overgeslagen (bestonden al).");
        }

        return self::SUCCESS;
    }

    private function ensureCollectionExists(): void
    {
        if (Collection::findByHandle('faq')) {
            $this->info('Collection "faq" bestaat al.');
            return;
        }

        $collection = Collection::make('faq')
            ->title('FAQ')
            ->routes('/faq/{slug}')
            ->sortDirection('asc')
            ->orderable(true);

        $collection->save();
        $this->info('Collection "faq" aangemaakt.');
    }

    private function ensureBlueprintExists(): void
    {
        $existing = Blueprint::find('collections.faq.faq');
        if ($existing) {
            $this->info('Blueprint "faq" bestaat al.');
            return;
        }

        $blueprint = Blueprint::make('faq')
            ->setNamespace('collections.faq')
            ->setContents([
                'title' => 'FAQ',
                'tabs' => [
                    'main' => [
                        'display' => 'Main',
                        'sections' => [
                            [
                                'fields' => [
                                    [
                                        'handle' => 'question',
                                        'field' => [
                                            'type' => 'text',
                                            'display' => 'Vraag',
                                            'validate' => 'required',
                                        ],
                                    ],
                                    [
                                        'handle' => 'answer',
                                        'field' => [
                                            'type' => 'bard',
                                            'display' => 'Antwoord',
                                            'validate' => 'required',
                                            'always_show_set_button' => false,
                                            'buttons' => ['h2', 'h3', 'bold', 'italic', 'unorderedlist', 'orderedlist', 'anchor'],
                                            'allow_source' => true,
                                        ],
                                    ],
                                    [
                                        'handle' => 'section_title',
                                        'field' => [
                                            'type' => 'text',
                                            'display' => 'Sectietitel',
                                            'instructions' => 'De titel van de FAQ-categorie',
                                        ],
                                    ],
                                    [
                                        'handle' => 'category',
                                        'field' => [
                                            'type' => 'select',
                                            'display' => 'Categorie',
                                            'options' => [
                                                'algemeen' => 'Algemeen',
                                                'webshops' => 'Webshops & Productbeheer',
                                                'klantbeheer' => 'Klantbeheer & Organisatie',
                                                'bestellen' => 'Bestellen & Betalen',
                                                'orderverwerking' => 'Orderverwerking & Facturatie',
                                                'puntensysteem' => 'Puntensysteem & Budgetbeheer',
                                                'integraties' => 'Integraties & Koppelingen',
                                                'rollen' => 'Rollen & Toegang',
                                                'btw' => 'BTW & Financieel',
                                                'modificaties' => 'Modificaties & Maatwerk',
                                                'waarom' => 'Waarom Texodata KMS',
                                            ],
                                            'validate' => 'required',
                                        ],
                                    ],
                                    [
                                        'handle' => 'badge',
                                        'field' => [
                                            'type' => 'select',
                                            'display' => 'Doelgroep',
                                            'options' => [
                                                'all' => 'Alle gebruikers',
                                                'distributor' => 'Distributeurs',
                                                'manager' => 'Managers',
                                                'wearer' => 'Medewerkers',
                                                'technical' => 'Technisch',
                                            ],
                                        ],
                                    ],
                                    [
                                        'handle' => 'sort_order',
                                        'field' => [
                                            'type' => 'integer',
                                            'display' => 'Sorteervolgorde',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $blueprint->save();
        $this->info('Blueprint "faq" aangemaakt.');
    }

    private function parseHtml(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $sections = $xpath->query('//div[contains(@class, "faq-section")]');
        $entries = [];

        foreach ($sections as $section) {
            // Section title
            $h2Nodes = $xpath->query('.//div[contains(@class, "section-header")]//h2', $section);
            $sectionTitle = $h2Nodes->length > 0 ? trim($h2Nodes->item(0)->textContent) : 'Onbekend';

            // Category
            $category = 'algemeen';
            foreach ($this->categoryMap as $title => $slug) {
                if (stripos($sectionTitle, rtrim($title, '?')) !== false) {
                    $category = $slug;
                    break;
                }
            }

            // Badge
            $badgeNodes = $xpath->query('.//div[contains(@class, "section-header")]//span[contains(@class, "badge")]', $section);
            $badge = 'all';
            if ($badgeNodes->length > 0) {
                $badgeClass = $badgeNodes->item(0)->getAttribute('class');
                foreach ($this->badgeMap as $class => $value) {
                    if (str_contains($badgeClass, $class)) {
                        $badge = $value;
                        break;
                    }
                }
                if (trim($badgeNodes->item(0)->textContent) === 'Technisch') {
                    $badge = 'technical';
                }
            }

            // FAQ items
            $faqItems = $xpath->query('.//div[contains(@class, "faq-item")]', $section);

            foreach ($faqItems as $faqItem) {
                $questionNodes = $xpath->query('.//button[contains(@class, "faq-question")]//span', $faqItem);
                if ($questionNodes->length === 0) continue;
                $question = trim($questionNodes->item(0)->textContent);

                $answerNodes = $xpath->query('.//div[contains(@class, "faq-answer-inner")]', $faqItem);
                if ($answerNodes->length === 0) continue;

                $answerHtml = '';
                foreach ($answerNodes->item(0)->childNodes as $child) {
                    $answerHtml .= $dom->saveHTML($child);
                }

                $entries[] = [
                    'question' => $question,
                    'answer' => trim($answerHtml),
                    'section_title' => $sectionTitle,
                    'category' => $category,
                    'badge' => $badge,
                ];
            }
        }

        return $entries;
    }

    /**
     * Convert HTML to Bard (ProseMirror) JSON content.
     *
     * Bard stores content as a ProseMirror document. We convert common HTML
     * elements (p, ul, ol, strong, em, a) to their ProseMirror equivalents.
     */
    private function htmlToBardContent(string $html): array
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>');
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        $content = [];

        foreach ($body->childNodes as $node) {
            $parsed = $this->parseNode($node);
            if ($parsed !== null) {
                $content[] = $parsed;
            }
        }

        return $content;

        return [
            [
                'type' => 'set',
                'attrs' => [
                    'id' => 'bard-content',
                    'enabled' => true,
                    'values' => [
                        'type' => 'text',
                    ],
                ],
            ],
            ...$content,
        ];
    }

    private function parseNode(\DOMNode $node): ?array
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $node->textContent;
            if (trim($text) === '') {
                return null;
            }
            return null; // Bare text nodes at top level are ignored
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return null;
        }

        $tagName = strtolower($node->nodeName);

        return match ($tagName) {
            'p' => [
                'type' => 'paragraph',
                'content' => $this->parseInlineChildren($node),
            ],
            'ul' => [
                'type' => 'bulletList',
                'content' => $this->parseListItems($node),
            ],
            'ol' => [
                'type' => 'orderedList',
                'attrs' => ['start' => 1],
                'content' => $this->parseListItems($node),
            ],
            'h2' => [
                'type' => 'heading',
                'attrs' => ['level' => 2],
                'content' => $this->parseInlineChildren($node),
            ],
            'h3' => [
                'type' => 'heading',
                'attrs' => ['level' => 3],
                'content' => $this->parseInlineChildren($node),
            ],
            'div' => $this->parseDivNode($node),
            default => [
                'type' => 'paragraph',
                'content' => $this->parseInlineChildren($node),
            ],
        };
    }

    private function parseDivNode(\DOMNode $node): ?array
    {
        // Highlight divs become paragraphs with bold text
        $class = $node->getAttribute('class') ?? '';
        if (str_contains($class, 'highlight')) {
            return [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'text',
                        'marks' => [['type' => 'bold']],
                        'text' => trim($node->textContent),
                    ],
                ],
            ];
        }

        // Other divs: try to parse children
        foreach ($node->childNodes as $child) {
            $parsed = $this->parseNode($child);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    private function parseListItems(\DOMNode $node): array
    {
        $items = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'li') {
                $items[] = [
                    'type' => 'listItem',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => $this->parseInlineChildren($child),
                        ],
                    ],
                ];
            }
        }
        return $items;
    }

    private function parseInlineChildren(\DOMNode $node): array
    {
        $content = [];
        foreach ($node->childNodes as $child) {
            $inlineNodes = $this->parseInlineNode($child);
            foreach ($inlineNodes as $inlineNode) {
                $content[] = $inlineNode;
            }
        }
        return $content ?: [['type' => 'text', 'text' => ' ']];
    }

    private function parseInlineNode(\DOMNode $node, array $marks = []): array
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $node->textContent;
            if ($text === '') {
                return [];
            }
            $result = ['type' => 'text', 'text' => $text];
            if (!empty($marks)) {
                $result['marks'] = $marks;
            }
            return [$result];
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return [];
        }

        $tagName = strtolower($node->nodeName);

        $newMarks = match ($tagName) {
            'strong', 'b' => [...$marks, ['type' => 'bold']],
            'em', 'i' => [...$marks, ['type' => 'italic']],
            'a' => [...$marks, ['type' => 'link', 'attrs' => ['href' => $node->getAttribute('href')]]],
            'code' => [...$marks, ['type' => 'code']],
            default => $marks,
        };

        $results = [];
        foreach ($node->childNodes as $child) {
            $childResults = $this->parseInlineNode($child, $newMarks);
            foreach ($childResults as $r) {
                $results[] = $r;
            }
        }

        return $results;
    }

    private function generateSlug(string $text): string
    {
        $slug = mb_strtolower($text);
        $slug = str_replace(
            ['é', 'ë', 'è', 'ê', 'ü', 'ú', 'ù', 'ö', 'ó', 'ò', 'ä', 'á', 'à', 'ï', 'í', 'ì'],
            ['e', 'e', 'e', 'e', 'u', 'u', 'u', 'o', 'o', 'o', 'a', 'a', 'a', 'i', 'i', 'i'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        if (strlen($slug) > 80) {
            $slug = substr($slug, 0, 80);
            $slug = preg_replace('/-[^-]*$/', '', $slug);
        }
        return $slug;
    }
}
