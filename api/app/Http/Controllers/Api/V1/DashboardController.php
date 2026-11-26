<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MaintenanceStatus;
use App\Enums\ViewingStatus;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\Lead;
use App\Models\MaintenanceRequest;
use App\Models\Post;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Service;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Viewing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Counted models. The key is the response key and the permission prefix (`<key>.view`). */
    private const COUNTED = [
        'posts' => Post::class,
        'categories' => Category::class,
        'services' => Service::class,
        'testimonials' => Testimonial::class,
        'team' => TeamMember::class,
        'gallery' => GalleryItem::class,
        'faqs' => Faq::class,
        'properties' => Property::class,
        'agents' => Agent::class,
    ];

    /**
     * Dashboard counts
     *
     * Row counts for every module the user may view, plus unread contact messages, new leads and upcoming
     * viewings (your own unless you can see all). Modules the user cannot view are left out.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $counts = [];

        foreach (self::COUNTED as $key => $model) {
            if ($user->can($key.'.view')) {
                $counts[$key] = $model::query()->count();
            }
        }

        if ($user->can('contact.view')) {
            $counts['unread_messages'] = ContactMessage::query()->whereNull('read_at')->count();
        }

        if ($user->can('leads.view')) {
            $counts['new_leads'] = Lead::query()->visibleTo($user)->where('status', 'new')->count();
        }

        if ($user->can('viewings.view')) {
            $counts['upcoming_viewings'] = Viewing::query()->visibleTo($user)
                ->whereIn('status', ViewingStatus::activeValues())->where('starts_at', '>=', now())->count();
        }

        if (config('rentals.enabled')) {
            if ($user->can('rent_charges.view')) {
                $counts['overdue_rent'] = RentCharge::query()->overdue()->count();
            }

            if ($user->can('maintenance.view')) {
                $counts['open_maintenance'] = MaintenanceRequest::query()
                    ->whereIn('status', [MaintenanceStatus::Open, MaintenanceStatus::InProgress])->count();
            }
        }

        return response()->json(['data' => $counts]);
    }
}
