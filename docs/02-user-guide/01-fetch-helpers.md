# Fetch helpers

All helpers perform **one** Flickr API request and return a `PagedResult`.

## ContactsFetcher

- `listPage($client, $page = 1, $perPage = null)` → `flickr.contacts.getList`
- `publicListPage($client, $userId, ...)` → `flickr.contacts.getPublicList`

## PeoplePhotosFetcher

- `listPage($client, $userId, ...)` → `flickr.people.getPhotos` (authenticated by default; applies config filters)
- `publicListPage($client, $userId, ...)` → `flickr.people.getPublicPhotos` (anonymous)

## PhotosetsFetcher

- `listPage($client, $userId, ...)` → `flickr.photosets.getList`
- `photosPage($client, $photosetId, ...)` → `flickr.photosets.getPhotos`

## GalleriesFetcher

- `listPage($client, $userId, ...)` → `flickr.galleries.getList`
- `photosPage($client, $galleryId, ...)` → `flickr.galleries.getPhotos`

## FavoritesFetcher

- `listPage($client, $userId, ...)` → `flickr.favorites.getList`

## TokenHealthProbe

- `probe($client)` → `flickr.test.login` → `TokenHealthResult`

## PagedResult

| Field | Meaning |
|---|---|
| `ok` | Flickr `stat=ok` |
| `page` / `pages` / `perPage` / `total` | Pagination |
| `items` | List of assoc arrays for this page |
| `errorCode` / `errorMessage` | Present when `ok` is false |
| `raw` | Underlying SDK `ApiResponseData` |
| `hasMorePages()` | `page < pages` when ok |
