QnixRESTfulBundle
======================

##### The simple bundle to replace context to class

### Supported Formats
- `application/json`
- `application/xml`
- `text/xml`
- `multipart/form-data`
- `application/x-www-form-urlencoded`


## Usage

### Automatic Request Format Detection

The bundle automatically detects and deserializes data from FormData, JSON, or XML into your DTO/Entity using reflection.

#### Example Controller
```php
namespace App\Controller;

use App\Request\SomeRequest;
use Qnix\RESTful\Resolver as QR;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

class ExampleController extends AbstractController
{
    #[Route('/api/example', methods: ['POST'])]
    public function example(
        #[MapRequestPayload(resolver: QR\JsonRawResolver::class)] SomeRequest $request
    ): Response {
        // $data will be automatically populated from FormData, JSON, or XML
        // ...
        return $this->json($data);
    }
}
```

#### Example Request
```php
namespace App\Request;

use Qnix\RESTful\Attribute as QA;
use Symfony\Component\Validator\Constraints as Assert;

class SomeRequest
{
    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    #[QA\Field(name: 'search', type: 'string', isOptional: true)]
    private ?string $search = null;

    #[QA\Field(name: 'count', type: 'int')]
    private string $count;

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
```
