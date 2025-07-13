QnixRESTfulBundle
======================

##### The simple bundle to replace context to class and provide JMS serialization with groups


## Installation
```bash
composer require qnix/restful-bundle
```


## Configuration

The bundle is automatically configured when installed. You can customize the configuration in your `config/services.yaml`:

```yaml
services:
    Qnix\RESTful\Infrastructure\EventSubscriber\ControllerEventSubscriber:
        tags:
            - { name: kernel.event_subscriber }
    
    Qnix\RESTful\Infrastructure\EventListener\ExceptionEventListener:
        tags:
            - { name: kernel.event_listener, event: kernel.exception }
```


## Usage

### Automatic Request Format Detection
The bundle automatically detects and deserialized data from FormData, JSON, or XML into your DTO & Entity using reflection for GET or POST methods.

#### Example Request
```php
namespace App\Request;

use Qnix\RESTful\Attribute as QA;
use Symfony\Component\Validator\Constraints as Assert;

final class SomeRequest
{
    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    #[QA\Field(name: 'search', type: 'string', isOptional: true)]
    private ?string $search = null;

    #[Assert\Positive]
    #[QA\Field(name: 'amount', type: 'float')]
    private float $amount;

    #[Assert\Valid]
    #[QA\Field(name: 'data', type: 'object', itemType: SomeRequestData::class)]
    private SomeRequestData $data;

    #[Assert\Valid]
    #[Assert\Type('array')]
    #[QA\Field(name: 'result', type: 'array_object', itemType: SomeRequestResult::class)]
    private array $result;

    // ... getters (only)
}
```

#### Example Controller
```php
namespace App\Controller;

use App\Request\SomeRequest;
use Qnix\RESTful\Resolver as QR;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

class ExampleController extends AbstractController
{
    #[Route('/api/example/single-one', methods: ['POST'])]
    public function singleOne(
        #[MapRequestPayload(resolver: QR\FormDataResolver::class)] SomeRequest $request,
    ): Response {
        // The $request will be automatically populated from FormData, JSON, or XML
        return $this->json($request);
    }

    #[Route('/api/example/single-two', methods: ['POST'])]
    public function singleTwo(
        #[MapRequestPayload(resolver: QR\JsonRawResolver::class)] SomeRequest $request,
    ): Response {
        // The $request will be automatically populated from FormData, JSON, or XML
        return $this->json($request);
    }

    #[Route('/api/example/array', methods: ['POST'])]
    public function array(
        #[MapRequestPayload(resolver: QR\JsonRawResolver::class, type: SomeRequest::class)] array $request,
    ): Response {
        // The $request will be automatically populated from FormData, JSON, or XML
        return $this->json($request);
    }
}
```

#### Available Resolvers
The bundle provides several resolvers for different data formats:
- `FormDataResolver` - Handles `multipart/form-data` and `application/x-www-form-urlencoded`
- `JsonRawResolver` - Handles `application/json`
- `XmlRawResolver` - Handles `application/xml` and `text/xml`


### JMS Serialization with Groups
The bundle provides powerful JMS serialization capabilities through the `Groups` attribute and `ControllerEventSubscriber`. This allows you to control which fields are serialized in your API responses based on serialization groups.

#### Entity with JMS Groups
Your entities should use JMS serialization annotations to define which groups each field belongs to:
```php
namespace App\Entity;

use JMS\Serializer\Annotation as JMS;

class User
{
    #[JMS\Groups(['list', 'detail'])]
    private int $id;

    #[JMS\Groups(['list', 'detail'])]
    private string $name;

    #[JMS\Groups(['detail'])]
    private string $email;

    #[JMS\Groups(['detail'])]
    private \DateTime $createdAt;

    // ... getters and setters
}
```

#### Using Groups Attribute
The `Groups` attribute allows you to specify which JMS serialization groups should be used for a specific controller method:
```php
namespace App\Controller;

use App\Entity\User;
use Qnix\RESTful\Attribute as QA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    #[Route('/api/users/{id}', methods: ['GET'])]
    #[QA\Groups(['detail'])]
    public function getUser(int $id): User
    {
        // The response will be automatically serialized using the 'detail' group
        // and wrapped in a standard response format
        return $this->userService->findById($id);
    }

    #[Route('/api/users', methods: ['GET'])]
    #[QA\Groups(['list'])]
    public function getUsers(): Response|array
    {
        // The response will be automatically serialized using the 'list' group
        // and null values will be included in the serialization
        return $this->userService->findAll();
    }
}
```

#### Groups Attribute Parameters
- `groupsSerialize` (array): List of JMS serialization groups to use
- `groupsReplacement` (array, optional): List of groups for replacement (if specified, automatic serialization is disabled)
- `serializeNull` (bool, default: false): Whether to include null values in serialization

#### Automatic Response Formatting
The `ControllerEventSubscriber` automatically:
- Detects the `Groups` attribute on controller methods
- Applies the specified serialization groups to the response
- Wraps the result in a standard JSON format: `{"status": "success", "result": ...}`
- Handles null value serialization based on the `serializeNull` parameter

This provides a consistent API response format while giving you fine-grained control over which fields are included in different API endpoints.
