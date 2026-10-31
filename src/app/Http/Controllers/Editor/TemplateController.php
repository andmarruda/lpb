<?php
namespace Andmarruda\Lpb\Http\Controllers\Editor;

use Andmarruda\Lpb\Contracts\TemplateRepositoryInterface;
use Andmarruda\Lpb\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Editor API for templates; view_path is the template's shell view.
 */
class TemplateController extends Controller
{
    private const RULES = [
        'name' => ['string', 'max:255'],
        'slug' => ['string', 'max:255'],
        'description' => ['nullable', 'string'],
        'view_path' => ['string', 'max:255'],
        'status' => ['in:active,inactive'],
        'seo' => ['nullable', 'array'],
    ];

    public function __construct(private TemplateRepositoryInterface $templates)
    {
    }

    public function index(): JsonResponse
    {
        $this->authorizeEdit();
        return new JsonResponse(['data' => $this->templates->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeEdit();
        $data = $request->validate(self::rules(['name', 'slug', 'view_path']));
        return new JsonResponse($this->templates->create($data), 201);
    }

    public function show(string $template): JsonResponse
    {
        $this->authorizeEdit();
        $found = $this->templates->find($template);
        return $found ? new JsonResponse($found) : $this->notFound('Template not found.');
    }

    public function update(Request $request, string $template): JsonResponse
    {
        $this->authorizeEdit();
        $updated = $this->templates->update($template, $request->validate(self::rules([])));
        return $updated ? new JsonResponse($updated) : $this->notFound('Template not found.');
    }

    public function destroy(string $template): JsonResponse
    {
        $this->authorizeEdit();
        return $this->templates->delete($template) ? new JsonResponse(null, 204) : $this->notFound('Template not found.');
    }

    public function setDefault(string $template): JsonResponse
    {
        $this->authorizeEdit();
        return $this->templates->setDefault($template)
            ? new JsonResponse($this->templates->find($template))
            : $this->notFound('Template not found.');
    }

    /**
     * @param string[] $required
     * @return array
     */
    private static function rules(array $required): array
    {
        $rules = [];
        foreach (self::RULES as $field => $fieldRules) {
            $rules[$field] = [in_array($field, $required, true) ? 'required' : 'sometimes', ...$fieldRules];
        }
        return $rules;
    }
}
