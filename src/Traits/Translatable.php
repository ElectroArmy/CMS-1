<?php

namespace Yab\Quarx\Traits;

use Yab\Quarx\Models\Translation;
use Yab\Quarx\Services\QuarxService;
use Stichoza\GoogleTranslate\TranslateClient;
use Yab\Quarx\Repositories\TranslationRepository;

trait Translatable
{
    /**
     * Get a translation
     *
     * @param  string $lang
     * @return mixed
     */
    public function translation($lang)
    {
        return Translation::where('entity_id', $this->id)
            ->where('entity_type', get_class($this))
            ->where('entity_data->lang', $lang)
            ->first();
    }

    /**
     * Get translation data
     *
     * @param  string $lang
     * @return array|null
     */
    public function translationData($lang)
    {
        $translation = $this->translation($lang);

        if ($translation) {
            return json_decode($translation->entity_data);
        }

        return null;
    }

    /**
     * Get a translations attribute
     *
     * @return array
     */
    public function getTranslationsAttribute()
    {
        $translationData = [];
        $translations = Translation::where('entity_id', $this->id)->where('entity_type', get_class($this))->get();

        foreach ($translations as $translation) {
            $translationData[] = $translation->data->attributes;
        }

        return $translationData;
    }

    /**
     * After the item is created in the database
     *
     * @param  Object $payload
     * @return void
     */
    public function afterCreate($payload)
    {
        if (config('quarx.auto-translate', false)) {
            $entry = $payload->toArray();

            unset($entry['created_at']);
            unset($entry['updated_at']);
            unset($entry['translations']);
            unset($entry['is_published']);
            unset($entry['published_at']);
            unset($entry['id']);

            foreach (config('quarx.languages') as $code => $language) {
                if ($code != config('quarx.default-language')) {
                    $tr = new TranslateClient(config('quarx.default-language'), $code);
                    $translation = [
                        'lang' => $code,
                        'template' => 'show',
                    ];

                    foreach ($entry as $key => $value) {
                        if (! empty($value)) {
                            $translation[$key] = json_decode(json_encode($tr->translate(strip_tags($value))));
                        }
                    }

                    if (isset($translation['url'])) {
                        $translation['url'] = app(QuarxService::class)->convertToURL($translation['url']);
                    }

                    $entityId = $payload->id;
                    $entityType = get_class($payload);
                    app(TranslationRepository::class)->createOrUpdate($entityId, $entityType, $translation);
                }
            }
        }
    }
}