# Qnix RESTful Bundle

![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%208.4-777BB4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-7.x%20%7C%208.x-000000?logo=symfony&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green.svg)

A lightweight Symfony bundle that turns incoming HTTP requests into typed, validated PHP objects and turns your
controller return values into a consistent JSON API response — all driven by PHP attributes.

It handles three concerns that every REST API repeats over and over:

1. **Request mapping** — deserialize `FormData`, `JSON`, or `XML` payloads into your request DTOs with strict type
   coercion, using nothing but PHP attributes and reflection.
2. **Response serialization** — serialize controller results through [JMS Serializer](https://jmsyst.com/libs/serializer)
   with serialization groups and wrap them in a standard envelope.
3. **Error handling** — convert mapping/validation failures into structured `422 Unprocessable Entity` JSON responses.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [How It Works](#how-it-works)
- [Quick Start](#quick-start)
- [Request Mapping](#request-mapping)
  - [The `Field` Attribute](#the-field-attribute)
  - [Supported Field Types](#supported-field-types)
  - [Resolvers](#resolvers)
  - [Optional and Nullable Fields](#optional-and-nullable-fields)
  - [Nested Objects and Collections](#nested-objects-and-collections)
  - [Mapping a Top-Level JSON Array](#mapping-a-top-level-json-array)
  - [Validation](#validation)
  - [Working with XML](#working-with-xml)
  - [Internationalized (i18n) Fields](#internationalized-i18n-fields)
- [Response Serialization](#response-serialization)
  - [The `Groups` Attribute](#the-groups-attribute)
  - [Response Envelope](#response-envelope)
  - [Opting Out with `groupsReplacement`](#opting-out-with-groupsreplacement)
- [Error Handling](#error-handling)
  - [Exception Types](#exception-types)
  - [Error Response Formats](#error-response-formats)
  - [Bypassing the Listener](#bypassing-the-listener)
- [End-to-End Example](#end-to-end-example)
- [Behavior Notes and Caveats](#behavior-notes-and-caveats)
- [Testing](#testing)
- [License](#license)
- [Author](#author)

---

## Features

- 🎯 **Attribute-driven mapping** — describe each request field with a single `#[Field]` attribute.
- 🔁 **Multiple formats** — dedicated resolvers for form data, raw JSON, and raw XML, plus query-string mapping for `GET`.
- 🧱 **Rich type system** — scalars, dates/times, enums, uploaded files, nested objects, and typed collections.
- ✅ **Built-in validation** — runs the Symfony Validator after mapping and reports violations in a structured shape.
- 📦 **Standard response envelope** — `{ "status": "success", "result": ... }` with JMS serialization groups.
- 🚨 **Structured errors** — automatic `422` JSON responses for missing fields, invalid data, and validation failures.
- 🔌 **Zero configuration** — services autowire on install; no bundle configuration required.

---

## Requirements

| Dependency                  | Version            |
|-----------------------------|--------------------|
| PHP                         | `>= 8.4`           |
| `symfony/framework-bundle`  | `^7.0` \|\| `^8.0` |
| `symfony/validator`         | `^7.0` \|\| `^8.0` |
| `jms/serializer-bundle`     | `^5.5`             |
| `ext-simplexml`             | `*`                |

---

## Installation

### 1. Install via Composer

```bash
composer require qnix/restful
```

### 2. Register the bundle

If you are not using Symfony Flex (which registers bundles automatically), add the bundle and its JMS dependency to
`config/bundles.php`:

```php
<?php

return [
    // ...
    JMS\SerializerBundle\JMSSerializerBundle::class => ['all' => true],
    Qnix\RESTful\QnixRESTfulBundle::class => ['all' => true],
];
```

### 3. That's it

The bundle imports its own service definitions on boot. The resolvers, the request transformer, the response
subscriber, and the exception listener are all registered and autowired automatically — there is no configuration tree
to fill in.

---

## How It Works

The bundle hooks into two stages of the Symfony HTTP kernel:

**Inbound (request → object):**

```
HTTP request
   │
   ▼
#[MapRequestPayload(resolver: ...)]  ──►  Resolver (Form / Json / Xml)
   │                                          │
   │                                          ▼
   │                                   RequestTransformer  ──►  maps & coerces each #[Field]
   │                                          │
   │                                          ▼
   │                                   Symfony Validator   ──►  throws ApiValidationFieldException on failure
   ▼
typed, validated request DTO injected into your controller argument
```

**Outbound (object → response):**

```
controller returns a value (not a Response)
   │
   ▼
ControllerEventSubscriber  ──►  reads #[Groups] on the action
   │
   ▼
JMS Serializer (with groups)  ──►  { "status": "success", "result": ... }
```

**On error:** any `ApiMissingFieldException`, `ApiWrongDataException`, or `ApiValidationFieldException` is caught by the
`ExceptionEventListener` and rendered as a `422` JSON response.

---

## Quick Start

**1. Define a request DTO** and annotate its properties:

```php
<?php declare(strict_types=1);

namespace App\Request;

use Qnix\RESTful\Attribute as QA;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateArticleRequest
{
    #[Assert\NotBlank]
    #[QA\Field(type: 'string')]
    private string $title;

    // Maps the incoming "body" key onto the $content property.
    #[Assert\NotBlank]
    #[QA\Field(name: 'body', type: 'string')]
    private string $content;

    #[QA\Field(type: 'bool', isOptional: true)]
    private bool $published = false;

    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }
    public function isPublished(): bool { return $this->published; }
}
```

> Mapping is done with reflection: properties are written directly, bypassing the constructor and setters. Your DTOs
> can be plain classes with typed properties — getters are only needed where *your* code reads the values.

**2. Pick a resolver** in your controller:

```php
<?php declare(strict_types=1);

namespace App\Controller;

use App\Request\CreateArticleRequest;
use Qnix\RESTful\Resolver as QR;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class ArticleController extends AbstractController
{
    #[Route('/api/articles', methods: ['POST'])]
    public function create(
        #[MapRequestPayload(resolver: QR\JsonRawResolver::class)]
        CreateArticleRequest $request,
    ): Response {
        // $request is fully mapped and validated by now.
        return $this->json([
            'title'     => $request->getTitle(),
            'published' => $request->isPublished(),
        ]);
    }
}
```

**3. Send a request:**

```json
{
  "title": "Hello, World",
  "body": "My first article.",
  "published": true
}
```

If `title` were missing, the client would receive a `422` with
`{"error":"Field 'title' is required.","message":"Field 'title' is required."}` — without the request ever reaching
your controller.

---

## Request Mapping

### The `Field` Attribute

`#[Qnix\RESTful\Attribute\Field]` is a property-level attribute that tells the transformer where to read a value and how
to coerce it.

```php
#[QA\Field(
    name: 'created_at',     // source key in the payload (defaults to the property name)
    type: 'datetime',       // how to interpret/coerce the value (default: 'string')
    itemType: null,         // target class for object/enum types (see below)
    isOptional: false,      // if true, a missing key is skipped instead of raising an error
    dateFormat: 'Y-m-d H:i',// custom format for 'date'/'datetime'
    xmlArrayField: null,    // repeated element name for 'array_object_xml'
)]
private \DateTime $createdAt;
```

| Parameter       | Type      | Default                     | Description                                                                        |
|-----------------|-----------|-----------------------------|------------------------------------------------------------------------------------|
| `name`          | `?string` | `null` → uses property name | The key to read from the request payload.                                          |
| `type`          | `string`  | `'string'`                  | One of the [supported types](#supported-field-types).                              |
| `itemType`      | `?string` | `null`                      | Target class for `object`/`array_object*` types, or the `BackedEnum` class for `enum`/`array_enum`. |
| `isOptional`    | `bool`    | `false`                     | When `true`, a missing key is silently skipped (the property keeps its default).    |
| `dateFormat`    | `?string` | `null`                      | Format string for `date` (default `Y-m-d`) and `datetime` (default `Y-m-d H:i:s`). |
| `xmlArrayField` | `?string` | `null`                      | Name of the repeated child element for `array_object_xml`.                          |

An unsupported `type` raises a `RuntimeException` at construction time, so typos surface immediately.

### Supported Field Types

| Type                 | Expected input                     | Produces                       | Notes                                                                 |
|----------------------|------------------------------------|--------------------------------|----------------------------------------------------------------------|
| `string`             | scalar                             | trimmed `string`               | Arrays are rejected.                                                  |
| `string_i18n`        | assoc array `lang ⇒ string`        | `array` `lang ⇒ trimmed string`| Language keys are lowercased; a `ru` key is dropped (see caveats).    |
| `date`               | `string`                           | `\DateTime` (time set to 00:00)| Empty → `null`. Format from `dateFormat` or `Y-m-d`.                 |
| `datetime`           | `string`                           | `\DateTime`                    | Empty → `null`. Format from `dateFormat` or `Y-m-d H:i:s`.           |
| `time`               | `string`                           | `int` (Unix timestamp)         | Empty → `null`. Parsed with `strtotime()`.                           |
| `int`                | scalar                             | `int`                          | Arrays are rejected.                                                  |
| `float`              | scalar                             | `float`                        | Arrays are rejected.                                                  |
| `bool`               | scalar / bool                      | `bool`                         | `"false"` (any case), `"0"`, and empty → `false`; other values → `true`. |
| `file`               | `UploadedFile`                     | `UploadedFile`                 | Must be an uploaded file instance.                                    |
| `enum`               | scalar                             | `\BackedEnum`                  | `itemType` must be a `BackedEnum`; resolved with `::from()`.          |
| `array`              | `array`                            | `array` (as-is)                | Pass-through; only checks it is an array.                             |
| `array_string`       | `array`                            | `string[]`                     | Each element trimmed and cast to string.                             |
| `array_int`          | `array`                            | `int[]`                        | Each element cast to int.                                            |
| `array_file`         | `array`                            | `UploadedFile[]`               | Each element must be an `UploadedFile`.                              |
| `array_enum`         | `array`                            | `\BackedEnum[]`                | `itemType` must be a `BackedEnum`; each resolved with `::from()`.    |
| `object`             | `array`                            | object of `itemType`           | Recursively mapped. Requires `itemType`.                             |
| `array_object`       | array of arrays                    | `object[]` of `itemType`       | Each element recursively mapped. Requires `itemType`.                |
| `array_object_xml`   | XML-shaped array                   | `object[]` of `itemType`       | Requires `itemType` and `xmlArrayField`. See [Working with XML](#working-with-xml). |
| `array_object_i18n`  | assoc array `key ⇒ array`          | `object[]` keyed by `key`      | Requires `itemType`; a `ru` key is dropped (see caveats).            |

> Type mismatches (e.g. an array where a scalar is expected, a non-`UploadedFile` for `file`, a missing `itemType`)
> raise an `ApiWrongDataException`, which the bundle renders as a `422`.

### Resolvers

A resolver is selected per argument through the `resolver` parameter of Symfony's `#[MapRequestPayload]` attribute. All
three resolvers live in `Qnix\RESTful\Resolver`, run the same transformer, and validate the result before injecting it.

| Resolver           | Typical content type(s)                                  | Body source (non-`GET`)                 | `GET` source       |
|--------------------|---------------------------------------------------------|-----------------------------------------|--------------------|
| `FormDataResolver` | `multipart/form-data`, `application/x-www-form-urlencoded` | POST fields **+** uploaded files       | query string       |
| `JsonRawResolver`  | `application/json`                                       | raw request body, JSON-decoded          | query string       |
| `XmlRawResolver`   | `application/xml`, `text/xml`                            | raw request body, parsed via SimpleXML  | query string       |

```php
use Qnix\RESTful\Resolver as QR;

// Form data / file uploads
public function upload(
    #[MapRequestPayload(resolver: QR\FormDataResolver::class)] UploadRequest $request,
): Response { /* ... */ }

// JSON body
public function save(
    #[MapRequestPayload(resolver: QR\JsonRawResolver::class)] SaveRequest $request,
): Response { /* ... */ }

// XML body
public function importXml(
    #[MapRequestPayload(resolver: QR\XmlRawResolver::class)] ImportRequest $request,
): Response { /* ... */ }
```

> For **`GET`** requests every resolver maps the **query string** onto the DTO instead of the body, which makes the same
> DTO + validation pipeline reusable for search and filter endpoints.

### Optional and Nullable Fields

A field is required by default. If its key is absent from the payload, the transformer throws
`ApiMissingFieldException`. Mark a field `isOptional: true` to skip it when the key is missing — the property simply
keeps whatever default it declares:

```php
#[QA\Field(type: 'string', isOptional: true)]
private ?string $search = null;
```

> **Important:** presence is checked with `isset()`, so a key whose value is explicitly `null` counts as *missing*.
> For fields that may arrive as `null`, use `isOptional: true` and give the property a default.

### Nested Objects and Collections

Compose DTOs by pointing `itemType` at another mapped class:

```php
final class OrderRequest
{
    #[Assert\Valid]
    #[QA\Field(type: 'object', itemType: CustomerRequest::class)]
    private CustomerRequest $customer;

    #[Assert\Valid]
    #[QA\Field(type: 'array_object', itemType: OrderLineRequest::class)]
    private array $lines;

    // getters...
}
```

```json
{
  "customer": { "name": "Ada Lovelace", "email": "ada@example.com" },
  "lines": [
    { "sku": "A-1", "qty": 2 },
    { "sku": "B-7", "qty": 1 }
  ]
}
```

Pair nested mapping with `#[Assert\Valid]` so that the Symfony Validator descends into the child objects.

### Mapping a Top-Level JSON Array

To accept a JSON array whose elements should each be mapped to a DTO, type the controller argument as `array` and pass
the element class through `#[MapRequestPayload(type: ...)]`. This is supported by `JsonRawResolver`:

```php
#[Route('/api/articles/batch', methods: ['POST'])]
public function batch(
    #[MapRequestPayload(resolver: QR\JsonRawResolver::class, type: CreateArticleRequest::class)]
    array $requests,
): Response {
    // $requests is CreateArticleRequest[]
    return $this->json(['count' => count($requests)]);
}
```

```json
[
  { "title": "First",  "body": "..." },
  { "title": "Second", "body": "..." }
]
```

### Validation

After mapping, every resolver runs the Symfony Validator against the resulting object. Annotate your DTO with standard
constraints from `Symfony\Component\Validator\Constraints`:

```php
#[Assert\NotBlank]
#[Assert\Email]
#[QA\Field(type: 'string')]
private string $email;

#[Assert\Positive]
#[QA\Field(type: 'float')]
private float $amount;
```

To run only a subset of constraints, use the standard `validationGroups` parameter of `#[MapRequestPayload]`:

```php
#[MapRequestPayload(resolver: QR\JsonRawResolver::class, validationGroups: ['create'])]
CreateArticleRequest $request,
```

When validation fails, an `ApiValidationFieldException` is thrown and rendered as a `422` (see
[Error Response Formats](#error-response-formats)).

### Working with XML

`XmlRawResolver` parses the body with `simplexml_load_string()` and normalizes it to an array, so the same `#[Field]`
attributes apply. XML *attributes* (SimpleXML's `@attributes`) are read transparently — a field is resolved from either
the element value or a same-named XML attribute.

For repeated elements, use `array_object_xml` together with `xmlArrayField`, which names the repeated child element:

```php
final class CatalogRequest
{
    #[QA\Field(type: 'array_object_xml', itemType: ProductRequest::class, xmlArrayField: 'product')]
    private array $products;

    // getters...
}
```

```xml
<catalog>
  <products>
    <product><sku>A-1</sku><name>Widget</name></product>
    <product><sku>B-7</sku><name>Gadget</name></product>
  </products>
</catalog>
```

### Internationalized (i18n) Fields

Two types help with translated payloads:

- **`string_i18n`** — a map of language code → string. Keys are lowercased and values trimmed:

  ```php
  #[QA\Field(type: 'string_i18n')]
  private array $title; // ['en' => 'Hello', 'uk' => 'Привіт']
  ```

- **`array_object_i18n`** — a map of language code → object, each mapped through `itemType`:

  ```php
  #[QA\Field(type: 'array_object_i18n', itemType: SeoRequest::class)]
  private array $seo; // ['en' => SeoRequest, 'uk' => SeoRequest]
  ```

> Both types intentionally drop a `ru` key if present. See [Behavior Notes and Caveats](#behavior-notes-and-caveats).

---

## Response Serialization

When a controller returns a value that is **not** a `Response`, the `ControllerEventSubscriber` serializes it with JMS
Serializer and wraps it in a standard envelope.

### The `Groups` Attribute

`#[Qnix\RESTful\Attribute\Groups]` is a method-level attribute that selects the JMS serialization groups for an action.

```php
use Qnix\RESTful\Attribute as QA;

#[Route('/api/users/{id}', methods: ['GET'])]
#[QA\Groups(['detail'])]
public function show(int $id): User
{
    return $this->users->find($id); // returned as an entity, not a Response
}
```

Your entities declare which fields belong to each group using JMS annotations:

```php
use JMS\Serializer\Annotation as JMS;

class User
{
    #[JMS\Groups(['list', 'detail'])]
    private int $id;

    #[JMS\Groups(['list', 'detail'])]
    private string $name;

    #[JMS\Groups(['detail'])]
    private string $email;

    // ...
}
```

| Parameter           | Type       | Default | Description                                                                                   |
|---------------------|------------|---------|-----------------------------------------------------------------------------------------------|
| `groupsSerialize`   | `string[]` | —       | **Required, non-empty.** JMS groups used to serialize the result.                             |
| `groupsReplacement` | `string[]` | `[]`    | When non-empty, the subscriber leaves your result untouched (see below).                       |
| `serializeNull`     | `bool`     | `false` | Whether `null` properties are included in the serialized output.                              |

An empty `groupsSerialize` (or any non-string/empty entry in either array) raises a `RuntimeException`.

### Response Envelope

With a `#[Groups]` attribute present and a non-`Response` return value, the response is serialized with the given groups
and wrapped as:

```json
{
  "status": "success",
  "result": {
    "id": 42,
    "name": "Ada Lovelace",
    "email": "ada@example.com"
  }
}
```

The subscriber leaves the response **unchanged** when any of the following is true:

- the controller returns a `Response`/`JsonResponse` (e.g. `return $this->json(...)`), or
- the controller returns `null`, or
- `groupsReplacement` is non-empty.

### Opting Out with `groupsReplacement`

Setting a non-empty `groupsReplacement` disables the automatic serialization and envelope for that action — the
subscriber steps aside and your controller is responsible for building and returning its own `Response`. Use this for
endpoints that need a different output shape while still documenting their intended groups via the attribute.

```php
#[QA\Groups(groupsSerialize: ['detail'], groupsReplacement: ['detail'])]
public function custom(): Response
{
    // Build and return your own Response; the subscriber won't touch it.
}
```

> Note: only whether `groupsReplacement` is empty matters to the subscriber; its contents are not used to serialize.

---

## Error Handling

The `ExceptionEventListener` listens on `kernel.exception` (priority `250`) and converts the bundle's API exceptions
into `422 Unprocessable Entity` JSON responses. Any other exception is left for Symfony to handle as usual.

### Exception Types

All live in `Qnix\RESTful\Infrastructure\Transformer\Exception` and extend `AbstractRequestException`.

| Exception                     | Thrown when                                            | HTTP status |
|-------------------------------|--------------------------------------------------------|-------------|
| `ApiMissingFieldException`    | A required field is absent from the payload.            | `422`       |
| `ApiWrongDataException`       | A value has the wrong type/shape for its `Field` type.  | `422`       |
| `ApiValidationFieldException` | The Symfony Validator reports one or more violations.   | `422`       |

### Error Response Formats

**Missing field** (`ApiMissingFieldException`):

```json
{
  "error": "Field 'title' is required.",
  "message": "Field 'title' is required."
}
```

**Wrong data** (`ApiWrongDataException`):

```json
{
  "error": "Invalid type of value. Expected type: 'int', 'array' given.",
  "message": "Invalid type of value. Expected type: 'int', 'array' given."
}
```

**Validation failure** (`ApiValidationFieldException`) — the violations are listed under `details`:

```json
{
  "error": "",
  "details": [
    { "parameter": "email",  "value": "not-an-email", "error": "This value is not a valid email address." },
    { "parameter": "amount", "value": -3,             "error": "This value should be positive." }
  ]
}
```

Each violation carries the offending `parameter` (property path), the rejected `value`, and the human-readable `error`
message produced by the constraint.

### Bypassing the Listener

Every API exception carries an `isProcessed` flag (`AbstractRequestException::setIsProcessed()`). If you catch one,
mark it processed, and re-throw it, the listener ignores it — letting you render a custom response while reusing the
same exception types:

```php
throw (new ApiWrongDataException('Custom handling'))->setIsProcessed(true);
```

---

## End-to-End Example

**Request DTO:**

```php
<?php declare(strict_types=1);

namespace App\Request;

use Qnix\RESTful\Attribute as QA;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateOrderRequest
{
    #[Assert\NotBlank]
    #[QA\Field(type: 'string')]
    private string $reference;

    #[Assert\Valid]
    #[QA\Field(type: 'object', itemType: CustomerRequest::class)]
    private CustomerRequest $customer;

    #[Assert\Valid]
    #[Assert\Count(min: 1)]
    #[QA\Field(type: 'array_object', itemType: OrderLineRequest::class)]
    private array $lines;

    #[QA\Field(type: 'datetime', isOptional: true, dateFormat: 'Y-m-d H:i')]
    private ?\DateTime $scheduledAt = null;

    public function getReference(): string { return $this->reference; }
    public function getCustomer(): CustomerRequest { return $this->customer; }
    public function getLines(): array { return $this->lines; }
    public function getScheduledAt(): ?\DateTime { return $this->scheduledAt; }
}
```

**Controller:**

```php
<?php declare(strict_types=1);

namespace App\Controller;

use App\Request\CreateOrderRequest;
use Qnix\RESTful\Attribute as QA;
use Qnix\RESTful\Resolver as QR;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController extends AbstractController
{
    #[Route('/api/orders', methods: ['POST'])]
    #[QA\Groups(['order:detail'])]
    public function create(
        #[MapRequestPayload(resolver: QR\JsonRawResolver::class)]
        CreateOrderRequest $request,
    ): object {
        // $request is mapped and validated; persist it and return an entity.
        $order = $this->orders->createFrom($request);

        return $order; // serialized with the 'order:detail' group and wrapped
    }
}
```

**Request:**

```http
POST /api/orders
Content-Type: application/json

{
  "reference": "ORD-1001",
  "customer": { "name": "Ada Lovelace", "email": "ada@example.com" },
  "lines": [
    { "sku": "A-1", "qty": 2 },
    { "sku": "B-7", "qty": 1 }
  ],
  "scheduledAt": "2026-07-01 09:30"
}
```

**Successful response:**

```json
{
  "status": "success",
  "result": {
    "id": 1001,
    "reference": "ORD-1001",
    "status": "pending"
  }
}
```

**Validation error response (`422`):**

```json
{
  "error": "",
  "details": [
    { "parameter": "customer.email", "value": "bad", "error": "This value is not a valid email address." }
  ]
}
```

---

## Behavior Notes and Caveats

- **`ru` keys are dropped.** Both `string_i18n` and `array_object_i18n` intentionally remove a `ru` language key from
  the input before mapping. If you need to accept Russian-language content, these types will silently discard it.
- **`null` is treated as missing.** Field presence is checked with `isset()`, so a key explicitly set to `null` is
  considered absent and will raise `ApiMissingFieldException` unless the field is `isOptional: true`.
- **`time` returns an integer.** The `time` type yields a Unix timestamp (`int`) from `strtotime()`, not a `\DateTime`.
- **Enums use `BackedEnum::from()`.** Unknown values throw a native `\ValueError`, which is *not* handled by the
  bundle's exception listener and will surface as a `500`. Validate/whitelist enum input upstream if that matters.
- **Validation errors have an empty `error` field.** For `ApiValidationFieldException`, the `error` (and `message`)
  fields are empty; the actionable information is in `details`.
- **`groupsReplacement` is a flag.** Only whether it is non-empty affects the response subscriber; the group names it
  contains are not used for serialization.
- **Resolvers require `#[MapRequestPayload]`.** The attribute is how a resolver is selected; the top-level JSON array
  feature additionally relies on its `type:` argument.
- **Worker runtimes.** `ControllerEventSubscriber` stores the active group settings on the service instance. Under
  standard PHP-FPM/mod_php this is per-request and safe. In long-running workers (FrankenPHP, RoadRunner, Swoole),
  ensure the kernel resets services between requests so an action without `#[Groups]` does not inherit a previous
  request's groups.

---

## Testing

The package ships with a PHPUnit configuration (`phpunit.dist.xml`) whose `unit` suite scans the `tests/` directory for
`*Test.php` files. Run it with:

```bash
composer install
vendor/bin/phpunit
```

---

## License

Released under the [MIT License](LICENSE).

## Author

**Mykola Vyhivskyi** — [qnixdev@gmail.com](mailto:qnixdev@gmail.com)
