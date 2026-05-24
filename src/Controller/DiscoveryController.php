<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Bootstrap;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Model\CityRepository;
use App\Model\EventRepository;
use App\Model\NeighborhoodRepository;

/**
 * Discovery libre (sans compte) — parcours produit Adrien
 * Porte de sortir/app/Http/Controllers/Api/DiscoveryController.php
 */
class DiscoveryController
{
    public function nearby(Request $request): void
    {
        $data = [
            'lat' => $request->query('lat'),
            'lng' => $request->query('lng'),
            'radius' => $request->query('radius'),
            'category' => $request->query('category'),
        ];
        $v = new Validator();
        [$ok, $errors, $clean] = $v->check($data, [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:100',
            'category' => 'nullable|string|max:60',
        ]);
        if (!$ok) {
            Response::validationError($errors);
            return;
        }

        $defaultRadius = (int) Bootstrap::config('default_radius_m', 3000);
        $maxRadius = (int) Bootstrap::config('max_radius_m', 10000);
        $radius = min((int) ($clean['radius'] ?? $defaultRadius), $maxRadius);

        $lat = (float) $clean['lat'];
        $lng = (float) $clean['lng'];
        $cat = !empty($clean['category']) ? $clean['category'] : null;

        $repo = new EventRepository();
        $rows = $repo->findNearby($lat, $lng, $radius, $cat);

        Response::json([
            'center' => ['lat' => $lat, 'lng' => $lng],
            'radius_m' => $radius,
            'count' => count($rows),
            'events' => $rows,
        ]);
    }

    public function byNeighborhood(Request $request): void
    {
        $slug = $request->param('slug');
        $citySlug = $request->query('city', 'toulouse');

        $cities = new CityRepository();
        $city = $cities->findBySlug($citySlug);
        if (!$city) {
            Response::error('City not found', 404);
            return;
        }

        $hoods = new NeighborhoodRepository();
        $neighborhood = $hoods->findBySlugAndCity($slug, (int) $city['id']);
        if (!$neighborhood) {
            Response::error('Neighborhood not found', 404);
            return;
        }

        $events = (new EventRepository())->findByNeighborhood((int) $neighborhood['id']);
        Response::json([
            'city' => $city['name'],
            'neighborhood' => $neighborhood['name'],
            'count' => count($events),
            'events' => $events,
        ]);
    }

    public function neighborhoods(Request $request): void
    {
        $slug = $request->param('slug');
        $city = (new CityRepository())->findBySlug($slug);
        if (!$city) {
            Response::error('City not found', 404);
            return;
        }
        $rows = (new NeighborhoodRepository())->listByCity((int) $city['id']);
        Response::json([
            'city' => $city['name'],
            'neighborhoods' => $rows,
        ]);
    }
}
