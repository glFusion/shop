<?php

declare(strict_types=1);

namespace Square\Models;

/**
 * An image file to use in Square catalogs. It can be associated with catalog
 * items, item variations, and categories.
 */
class CatalogImage implements \JsonSerializable
{
    /**
     * @var string|null
     */
    private $name;

    /**
     * @var string|null
     */
    private $url;

    /**
     * @var string|null
     */
    private $caption;

    /**
     * Returns Name.
     *
     * The internal name to identify this image in calls to the Square API.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Sets Name.
     *
     * The internal name to identify this image in calls to the Square API.
     *
     * @maps name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * Returns Url.
     *
     * The URL of this image, generated by Square after an image is uploaded
     * using the [CreateCatalogImage](#endpoint-Catalog-CreateCatalogImage) endpoint.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Sets Url.
     *
     * The URL of this image, generated by Square after an image is uploaded
     * using the [CreateCatalogImage](#endpoint-Catalog-CreateCatalogImage) endpoint.
     *
     * @maps url
     */
    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    /**
     * Returns Caption.
     *
     * A caption that describes what is shown in the image. Displayed in the
     * Square Online Store. This is a searchable attribute for use in applicable query filters.
     */
    public function getCaption(): ?string
    {
        return $this->caption;
    }

    /**
     * Sets Caption.
     *
     * A caption that describes what is shown in the image. Displayed in the
     * Square Online Store. This is a searchable attribute for use in applicable query filters.
     *
     * @maps caption
     */
    public function setCaption(?string $caption): void
    {
        $this->caption = $caption;
    }

    /**
     * Encode this object to JSON
     *
     * @return mixed
     */
    public function jsonSerialize()
    {
        $json = [];
        $json['name']    = $this->name;
        $json['url']     = $this->url;
        $json['caption'] = $this->caption;

        return array_filter($json, function ($val) {
            return $val !== null;
        });
    }
}