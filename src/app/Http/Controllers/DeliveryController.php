<?php
namespace Andmarruda\Lpb\Http\Controllers;

use Andmarruda\Lpb\Contracts\MenuRepositoryInterface;
use Andmarruda\Lpb\Delivery\PageDeliveryService;
use Andmarruda\Lpb\Tree\MenuTree;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public delivery API: published pages, menus and signed previews.
 */
class DeliveryController extends Controller
{
    public function __construct(private PageDeliveryService $delivery)
    {
    }

    /**
     * The published payload of a page; 304 when the client has it already.
     */
    public function page(Request $request, string $slug): Response
    {
        $payload = $this->delivery->published($slug);
        if ($payload === null) {
            return $this->notFound("Page '{$slug}' is not published.");
        }

        $response = new JsonResponse($payload);
        $response->setEtag(sha1($payload['revision_id'] . ':' . $payload['design_version']));
        $response->isNotModified($request);
        return $response;
    }

    public function menu(MenuRepositoryInterface $menus, string $location): JsonResponse
    {
        $menu = $menus->findByLocation($location);
        $menu = $menu ? $menus->withItems($menu['id']) : null;
        if ($menu === null) {
            return $this->notFound("No menu at '{$location}'.");
        }

        return new JsonResponse([
            'id' => $menu['id'],
            'name' => $menu['name'],
            'slug' => $menu['slug'],
            'location' => $menu['location'],
            'items' => MenuTree::nest($menu['items'] ?? []),
        ]);
    }

    /**
     * The working tree of a page; reached through a signed, expiring URL.
     */
    public function preview(Encrypter $encrypter, string $token): JsonResponse
    {
        try {
            $pageId = $encrypter->decryptString($token);
        } catch (DecryptException) {
            return $this->notFound('Invalid preview token.');
        }

        $payload = $this->delivery->preview($pageId);
        if ($payload === null) {
            return $this->notFound('Page not found.');
        }

        return (new JsonResponse($payload))->header('Cache-Control', 'no-store, private');
    }
}
