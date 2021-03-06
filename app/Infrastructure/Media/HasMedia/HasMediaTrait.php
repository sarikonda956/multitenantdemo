<?php

namespace App\Infrastructure\Media\HasMedia;

use Illuminate\Http\File;
use App\Domain\Media\Entities\Media;
use Illuminate\Support\Collection;
use App\Infrastructure\Doctrine\Repositories\MediaRepository;
use Illuminate\Support\Facades\Validator;
use App\Infrastructure\Media\FileAdder\FileAdder;
use App\Infrastructure\Media\Conversion\Conversion;
use App\Infrastructure\Media\FileAdder\FileAdderFactory;
use App\Infrastructure\Media\HasMedia\Interfaces\HasMedia;
use App\Infrastructure\Media\Events\CollectionHasBeenCleared;
use App\Infrastructure\Media\Exceptions\MediaCannotBeDeleted;
use App\Infrastructure\Media\Exceptions\MediaCannotBeUpdated;
use App\Infrastructure\Media\Exceptions\FileCannotBeAdded\UnreachableUrl;
use App\Infrastructure\Media\Exceptions\FileCannotBeAdded\InvalidBase64Data;
use App\Infrastructure\Media\Exceptions\FileCannotBeAdded\MimeTypeNotAllowed;

trait HasMediaTrait
{
    /** @var array */
    public $mediaConversions = [];

    /** @var bool */
    protected $deletePreservingMedia = false;

    /** @var array */
    protected $unAttachedMediaLibraryItems = [];

//    public static function bootHasMediaTrait()
//    {
//        static::deleted(function (HasMedia $entity) {
//            if ($entity->shouldDeletePreservingMedia()) {
//                return;
//            }
//
//            $entity->media()->get()->each->delete();
//        });
//    }
//
//    /**
//     * Set the polymorphic relation.
//     *
//     * @return mixed
//     */
//    public function media()
//    {
//        return $this->morphMany(config('medialibrary.media_model'), 'model');
//    }

    /**
     * Add a file to the medialibrary.
     *
     * @param string|\Symfony\Component\HttpFoundation\File\UploadedFile $file
     *
     * @return FileAdder
     */
    public function addMedia($file)
    {
        return app(FileAdderFactory::class)->create($this, $file);
    }

    /**
     * Add a file from a request.
     *
     * @param string $key
     *
     * @return FileAdder
     */
    public function addMediaFromRequest(string $key)
    {
        return app(FileAdderFactory::class)->createFromRequest($this, $key);
    }

    /**
     * Add multiple files from a request by keys.
     *
     * @param string[] $keys
     *
     * @return FileAdder[]
     */
    public function addMultipleMediaFromRequest(array $keys)
    {
        return app(FileAdderFactory::class)->createMultipleFromRequest($this, $keys);
    }

    /**
     * Add all files from a request.
     *
     * @return FileAdder[]
     */
    public function addAllMediaFromRequest()
    {
        return app(FileAdderFactory::class)->createAllFromRequest($this);
    }

    /**
     * Add a remote file to the medialibrary.
     *
     * @param string $url
     * @param string|array ...$allowedMimeTypes
     *
     * @return FileAdder
     *
     */
    public function addMediaFromUrl(string $url, ...$allowedMimeTypes)
    {
        if (! $stream = @fopen($url, 'r')) {
            throw UnreachableUrl::create($url);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'media-library');
        file_put_contents($tmpFile, $stream);

        $this->guardAgainstInvalidMimeType($tmpFile, $allowedMimeTypes);

        $filename = basename(parse_url($url, PHP_URL_PATH));

        return app(FileAdderFactory::class)
            ->create($this, $tmpFile)
            ->usingName(pathinfo($filename, PATHINFO_FILENAME))
            ->usingFileName($filename);
    }

    /**
     * Add a base64 encoded file to the medialibrary.
     *
     * @param string $base64data
     * @param string|array ...$allowedMimeTypes
     *
     * @throws InvalidBase64Data
     * @throws \App\Infrastructure\Media\Exceptions\FileCannotBeAdded
     *
     * @return FileAdder
     */
    public function addMediaFromBase64(string $base64data, ...$allowedMimeTypes)
    {
        // strip out data uri scheme information (see RFC 2397)
        if (strpos($base64data, ';base64') !== false) {
            list(, $base64data) = explode(';', $base64data);
            list(, $base64data) = explode(',', $base64data);
        }

        // strict mode filters for non-base64 alphabet characters
        if (base64_decode($base64data, true) === false) {
            throw InvalidBase64Data::create();
        }

        // decoding and then reeconding should not change the data
        if (base64_encode(base64_decode($base64data)) !== $base64data) {
            throw InvalidBase64Data::create();
        }

        $binaryData = base64_decode($base64data);

        // temporarily store the decoded data on the filesystem to be able to pass it to the fileAdder
        $tmpFile = tempnam(sys_get_temp_dir(), 'medialibrary');
        file_put_contents($tmpFile, $binaryData);

        $this->guardAgainstInvalidMimeType($tmpFile, $allowedMimeTypes);

        $file = app(FileAdderFactory::class)
            ->create($this, $tmpFile);

        return $file;
    }

    /**
     * Copy a file to the medialibrary.
     *
     * @param string|\Symfony\Component\HttpFoundation\File\UploadedFile $file
     *
     * @return FileAdder
     */
    public function copyMedia($file)
    {
        return $this->addMedia($file)->preservingOriginal();
    }

    /*
     * Determine if there is media in the given collection.
     */
    public function hasMedia(string $collectionName = 'default'): bool
    {
        return count($this->getMedia($collectionName)) ? true : false;
    }

    /**
     * Get media collection by its collectionName.
     *
     * @param string $collectionName
     * @param array|callable $filters
     *
     * @return \Illuminate\Support\Collection
     */
    public function getMedia(string $collectionName = 'default', $filters = []): Collection
    {
        return app(MediaRepository::class)->getCollection($this, $collectionName, $filters);
    }

    /**
     * Get the first media item of a media collection.
     *
     * @param string $collectionName
     * @param array $filters
     *
     * @return Media|null
     */
    public function getFirstMedia(string $collectionName = 'default', array $filters = [])
    {
        $media = $this->getMedia($collectionName, $filters);

        return $media->first();
    }

    /*
     * Get the url of the image for the given conversionName
     * for first media for the given collectionName.
     * If no profile is given, return the source's url.
     */
    public function getFirstMediaUrl(string $collectionName = 'default', string $conversionName = ''): string
    {
        $media = $this->getFirstMedia($collectionName);

        if (! $media) {
            return '';
        }

        return $media->getUrl($conversionName);
    }

    /*
     * Get the url of the image for the given conversionName
     * for first media for the given collectionName.
     * If no profile is given, return the source's url.
     */
    public function getFirstMediaPath(string $collectionName = 'default', string $conversionName = ''): string
    {
        $media = $this->getFirstMedia($collectionName);

        if (! $media) {
            return '';
        }

        return $media->getPath($conversionName);
    }

    /**
     * Update a media collection by deleting and inserting again with new values.
     *
     * @param array $newMediaArray
     * @param string $collectionName
     *
     * @return \Illuminate\Support\Collection
     *
     * @throws MediaCannotBeUpdated
     */
    public function updateMedia(array $newMediaArray, string $collectionName = 'default'): Collection
    {
        $this->removeMediaItemsNotPresentInArray($newMediaArray, $collectionName);

        return collect($newMediaArray)
            ->map(function (array $newMediaItem) use ($collectionName) {
                static $orderColumn = 1;

                $mediaClass = config('medialibrary.media_model');
                $currentMedia = $mediaClass::findOrFail($newMediaItem['id']);

                if ($currentMedia->getCollectionName() != $collectionName) {
                    throw MediaCannotBeUpdated::doesNotBelongToCollection($collectionName, $currentMedia);
                }

                if (array_key_exists('name', $newMediaItem)) {
                    $currentMedia->name = $newMediaItem['name'];
                }

                if (array_key_exists('custom_properties', $newMediaItem)) {
                    $currentMedia->setCustomProperties($newMediaItem['custom_properties']);
                }

                $currentMedia->setOrderColumn($orderColumn++);

                $currentMedia->save();

                return $currentMedia;
            });
    }

    /**
     * @param array $newMediaArray
     * @param string $collectionName
     */
    protected function removeMediaItemsNotPresentInArray(array $newMediaArray, string $collectionName = 'default')
    {
        $this->getMedia($collectionName)
            ->reject(function (Media $currentMediaItem) use ($newMediaArray) {
                return in_array($currentMediaItem->getId(), array_column($newMediaArray, 'id'));
            })
            ->each->delete();
    }

    /**
     * Remove all media in the given collection.
     *
     * @param string $collectionName
     *
     * @return $this
     */
    public function clearMediaCollection(string $collectionName = 'default')
    {
        $this->getMedia($collectionName)
            ->each->delete();

        event(new CollectionHasBeenCleared($this, $collectionName));

        if ($this->mediaIsPreloaded()) {
            unset($this->media);
        }

        return $this;
    }

    /**
     * Remove all media in the given collection except some.
     *
     * @param string $collectionName
     * @param Media[]|\Illuminate\Support\Collection $excludedMedia
     *
     * @return $this
     */
    public function clearMediaCollectionExcept(string $collectionName = 'default', $excludedMedia = [])
    {
        $excludedMedia = collect($excludedMedia);

        if ($excludedMedia->isEmpty()) {
            return $this->clearMediaCollection($collectionName);
        }

        $this->getMedia($collectionName)
            ->reject(function (Media $media) use ($excludedMedia) {
                return $excludedMedia->where('id', $media->getId())->count();
            })
            ->each->delete();

        if ($this->mediaIsPreloaded()) {
            unset($this->media);
        }

        return $this;
    }

    /**
     * Delete the associated media with the given id.
     * You may also pass a media object.
     *
     * @param int|Media $mediaId
     *
     * @throws MediaCannotBeDeleted
     */
    public function deleteMedia($mediaId)
    {
        if ($mediaId instanceof Media) {
            $mediaId = $mediaId->getId();
        }

        $media = $this->media->find($mediaId);

        if (! $media) {
            throw MediaCannotBeDeleted::doesNotBelongToModel($media, $this);
        }

        $media->delete();
    }

    /*
     * Add a conversion.
     */
    public function addMediaConversion(string $name): Conversion
    {
        $conversion = Conversion::create($name);

        $this->mediaConversions[] = $conversion;

        return $conversion;
    }

    /**
     * Delete the model, but preserve all the associated media.
     *
     * @return bool
     */
    public function deletePreservingMedia(): bool
    {
        $this->deletePreservingMedia = true;

        return $this->delete();
    }

    /**
     * Determines if the media files should be preserved when the media object gets deleted.
     *
     * @return Media
     */
    public function shouldDeletePreservingMedia()
    {
        return $this->deletePreservingMedia ?? false;
    }

    protected function mediaIsPreloaded(): bool
    {
        return $this->relationLoaded('media');
    }

    /**
     * Cache the media on the object.
     *
     * @param string $collectionName
     *
     * @return mixed
     */
    public function loadMedia(string $collectionName)
    {
        $collection = $this->exists
            ? $this->media
            : collect($this->unAttachedMediaLibraryItems)->pluck('media');

        return $collection
            ->filter(function (Media $mediaItem) use ($collectionName) {
                if ($collectionName == '') {
                    return true;
                }

                return $mediaItem->collection_name === $collectionName;
            })
            ->sortBy('order_column')
            ->values();
    }

	/**
	 * @param Media $media
	 * @param FileAdder $fileAdder
	 *
	 */
    public function prepareToAttachMedia(Media $media, FileAdder $fileAdder)
    {
        $this->unAttachedMediaLibraryItems[] = compact('media', 'fileAdder');
    }

    public function processUnattachedMedia(callable $callable)
    {
        foreach ($this->unAttachedMediaLibraryItems as $item) {
            $callable($item['media'], $item['fileAdder']);
        }

        $this->unAttachedMediaLibraryItems = [];
    }

	/**
	 * @param string $file
	 * @param array ...$allowedMimeTypes
	 * @throws MimeTypeNotAllowed
	 */
    protected function guardAgainstInvalidMimeType(string $file, ...$allowedMimeTypes)
    {
        $allowedMimeTypes = array_flatten($allowedMimeTypes);

        if (empty($allowedMimeTypes)) {
            return;
        }

        $validation = Validator::make(
            ['file' => new File($file)],
            ['file' => 'mimetypes:'.implode(',', $allowedMimeTypes)]
        );

        if ($validation->fails()) {
            throw MimeTypeNotAllowed::create($file, $allowedMimeTypes);
        }
    }
}
