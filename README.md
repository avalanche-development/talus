# talus

PHP microframework that leverages [swagger](http://swagger.io/) documentation to handle routing.

[![Build Status](https://travis-ci.org/avalanche-development/talus.svg?branch=master)](https://travis-ci.org/avalanche-development/talus)
[![Code Climate](https://codeclimate.com/github/avalanche-development/talus/badges/gpa.svg)](https://codeclimate.com/github/avalanche-development/talus)
[![Test Coverage](https://codeclimate.com/github/avalanche-development/talus/badges/coverage.svg)](https://codeclimate.com/github/avalanche-development/talus/coverage)

## Installation

It's recommended that you use [Composer](https://getcomposer.org/) to install talus.

```bash
$ composer require avalanche-development/talus
```

talus requires PHP 5.6 or newer.

## Usage

This microframework uses [swagger-router-middleware](https://github.com/avalanche-development/swagger-router-middleware) to parse and understand a provided swagger documentation. It also uses [peel](https://github.com/avalanche-development/peel) http exceptions and [crash-pad](https://github.com/avalanche-development/crash-pad) to standardize error responses. To instantiate talus, you'll need to provide the swagger document in the form of an array.

```php
$talus = new AvalancheDevelopment\Talus\Talus([..swagger..]);
```

The parsed swagger information is available via the `swagger` attribute on the request. In your application, you can access this information like so.

```php
function ($request, $response) {
  $someParameter = $request->getAttribute('swagger')['params']['someParameter'];
}
```

### Controllers

Controllers are attached to routes by the operationId in the swagger spec. Each operation should have a unique operationId, and this will inform talus which controller to invoke. Controllers must be callable and have a `function ($request, $response)` interface.

```php
$talus->addController('getThing', function ($request, $response) {
  // get that thing
});

$talus->addController('getComplexThing', function ($request, $response) use ($db) {
  return (new Controller($db))->getComplexThing($request, $response);
});
```

### Middleware

Middleware can be added onto the stack and will be executed in the order they are added. As a note, the first executed piece of middleware will always be `swagger-router-middleware` (which will give you a `swagger` attribute with all sorts of goodies in it) and the last executed piece will always be the provided controller callable.

```
$talus->addMiddleware(function ($request, $response, $next) {
  // do something
  return $next($request, $response);
});
```

### Error Handling

Any exceptions thrown during the stack, which includes routing errors and request body parsing failures, will be caught and passed through to the error handler. Some of these exceptions implement `HttpErrorInterface` `peel`, making them easier to deal with. The default error handler `crash-pad` provided will handle this and return a standard response, though you can totally roll your own.

```
$talus->setErrorHandler(function ($request, $response, $exception) {
  // do something with that exception
  return $response;
});
```

It is recommended that you throw exceptions, especially `peel` ones, within your controllers instead of trying to handle the error yourself. This will keep things standardized.

### Execution

Once the controllers are set, middleware is stacked on, and error handler is customized, all that's left to do is to run the app. This will walk through the middleware stack and execute the appropriate controller, then walk back through middleware and output the provided response object. If there are any problems along the way the error handler should capture them and return a relevant error message.

```
$talus = new AvalancheDevelopment\Talus\Talus([..swagger..]);

$talus->addController(..callback..);
$talus->addMiddleware(..callback..);

$talus->run();
```

### Documentation Route

This is a feature of `swagger-router-middleware`. If the standard 'documentation route' is detected (path of /api-docs), the rest of the stack is immediately skipped and the swagger spec is returned as json. An error with json_encode will throw a standard \Exception.

### Tests

To execute the test suite, you'll need phpunit (and to install package with dev dependencies).

```bash
$ phpunit
```

## License

talus is licensed under the MIT license. See [License File](LICENSE) for more information.
