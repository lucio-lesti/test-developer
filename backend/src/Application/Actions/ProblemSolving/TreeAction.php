<?php

declare(strict_types=1);

namespace App\Application\Actions\ProblemSolving;

use App\Application\Actions\Action;
use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use App\Domain\ProblemSolving\TreeTraverser;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class TreeAction extends Action
{
    private const ALLOWED_OPERATIONS = ['leaves', 'depth', 'path'];

    private TreeTraverser $traverser;

    public function __construct(LoggerInterface $logger, TreeTraverser $traverser)
    {
        parent::__construct($logger);
        $this->traverser = $traverser;
    }

    protected function action(): Response
    {
        $body = $this->getFormData();
        $errors = [];

        if (!is_array($body)) {
            $errors['_body'] = ['Request body must be a JSON object.'];
            return $this->validationFailure($errors);
        }

        $tree = $body['tree'] ?? null;
        $operation = isset($body['operation']) ? (string) $body['operation'] : '';

        if (!in_array($operation, self::ALLOWED_OPERATIONS, true)) {
            $errors['operation'] = ['Must be one of: ' . implode(', ', self::ALLOWED_OPERATIONS) . '.'];
        }

        if ($operation === 'path' && (!isset($body['target']) || !is_string($body['target']) || $body['target'] === '')) {
            $errors['target'] = ['Must be a non-empty string when operation = "path".'];
        }

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        try {
            switch ($operation) {
                case 'leaves':
                    return $this->respondWithData(['leaves' => $this->traverser->leaves($tree)]);
                case 'depth':
                    return $this->respondWithData(['depth' => $this->traverser->depth($tree)]);
                case 'path':
                default:
                    $path = $this->traverser->path($tree, (string) $body['target']);
                    return $this->respondWithData(['path' => $path]);
            }
        } catch (InvalidArgumentException $e) {
            return $this->validationFailure(['tree' => [$e->getMessage()]]);
        }
    }

    /**
     * @param array<string,string[]> $errors
     */
    private function validationFailure(array $errors): Response
    {
        return $this->respond(new ActionPayload(
            422,
            ['fields' => $errors],
            new ActionError(ActionError::VALIDATION_ERROR, 'Invalid input.')
        ));
    }
}
