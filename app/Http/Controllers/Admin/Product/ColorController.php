<?php

namespace App\Http\Controllers\Admin\Product;

use App\Contracts\Repositories\ColorRepositoryInterface;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\ColorRequest;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ColorController extends BaseController
{
    public function __construct(
        private readonly ColorRepositoryInterface $colorRepo,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View
     */
    public function index(Request|null $request, string $type = null): View
    {
        $colors = $this->colorRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            searchValue: $request->get('searchValue'),
            dataLimit: getWebConfig(name: 'pagination_limit')
        );
        return view('admin-views.color.list', compact('colors'));
    }

    /**
     * @return View
     */
    public function getAddView(): View
    {
        return view('admin-views.color.add-new');
    }

    /**
     * @param ColorRequest $request
     * @return RedirectResponse
     */
    public function add(ColorRequest $request): RedirectResponse
    {
        $dataArray = [
            'name' => $request->name,
            'code' => strtoupper($request->code),
        ];

        $this->colorRepo->add(data: $dataArray);
        
        // Clear color cache
        Cache::forget(CACHE_FOR_ALL_COLOR_LIST);

        ToastMagic::success(translate('color_added_successfully'));
        return redirect()->route('admin.color.list');
    }

    /**
     * @param string|int $id
     * @return View|RedirectResponse
     */
    public function getUpdateView(string|int $id): View|RedirectResponse
    {
        $color = $this->colorRepo->getFirstWhere(params: ['id' => $id]);
        
        if (!$color) {
            ToastMagic::error(translate('color_not_found'));
            return redirect()->route('admin.color.list');
        }
        
        return view('admin-views.color.edit', compact('color'));
    }

    /**
     * @param ColorRequest $request
     * @return RedirectResponse
     */
    public function update(ColorRequest $request): RedirectResponse
    {
        $dataArray = [
            'name' => $request->name,
            'code' => strtoupper($request->code),
        ];

        $this->colorRepo->update(id: $request->id, data: $dataArray);
        
        // Clear color cache
        Cache::forget(CACHE_FOR_ALL_COLOR_LIST);

        ToastMagic::success(translate('color_updated_successfully'));
        return redirect()->route('admin.color.list');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(Request $request): JsonResponse
    {
        $this->colorRepo->delete(params: ['id' => $request['id']]);
        
        // Clear color cache
        Cache::forget(CACHE_FOR_ALL_COLOR_LIST);
        
        return response()->json(['message' => translate('color_deleted_successfully')]);
    }
}
