<?php

namespace App\Http\Controllers;

use App\Models\BibleReading;
use App\Models\People;
use App\Models\Respuesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Muestra el dashboard principal del administrador
     */
    public function dashboard()
    {
        // Métricas básicas
        $totalUsers = People::count();
        $todayUsers = People::whereDate('created_at', today())->count();
        $weekUsers = People::where('created_at', '>=', now()->subWeek())->count();
        $monthUsers = People::where('created_at', '>=', now()->subMonth())->count();

        // Usuarios activos (ejemplo: usuarios que han completado la encuesta)
        $activeUsers = People::whereHas('respuestas')->distinct()->count();

        // Encuestas completadas
        $completedSurveys = Respuesta::distinct('people_id')->count('people_id');

        // Usuarios que han leído la Biblia (al menos 1 capítulo)
        $bibleReaders = BibleReading::distinct('people_id')->count('people_id');

        // Lecturas de Biblia totales
        $totalBibleReadings = BibleReading::count();

        // Lecturas de Biblia esta semana
        $weekBibleReadings = BibleReading::where('created_at', '>=', now()->subWeek())->count();

        // Top 5 libros más leídos
        $topBooks = BibleReading::select('book', DB::raw('count(*) as total'))
            ->groupBy('book')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();

        // Suscripciones (temporal, hasta que implementemos el sistema de pagos)
        $premiumUsers = 0;
        $freeUsers = $totalUsers;

        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'totalUsers' => $totalUsers,
                'todayUsers' => $todayUsers,
                'weekUsers' => $weekUsers,
                'monthUsers' => $monthUsers,
                'activeUsers' => $activeUsers,
                'completedSurveys' => $completedSurveys,
                'bibleReaders' => $bibleReaders,
                'totalBibleReadings' => $totalBibleReadings,
                'weekBibleReadings' => $weekBibleReadings,
                'topBooks' => $topBooks,
                'premiumUsers' => $premiumUsers,
                'freeUsers' => $freeUsers,
            ],
        ]);
    }

    /**
     * Muestra el funnel de conversión
     */
    public function funnel()
    {
        // Paso 1: Usuarios registrados
        $registered = People::count();

        // Paso 2: Usuarios que completaron la encuesta
        $completedSurvey = Respuesta::distinct('people_id')->count('people_id');

        // Paso 3: Usuarios que completaron una oración (temporal, 0 hasta implementar oraciones)
        $completedPrayer = 0;

        // Paso 4: Usuarios premium (temporal, 0 hasta implementar suscripciones)
        $subscribed = 0;

        // Calcular tasas de conversión
        $surveyRate = $registered > 0 ? round(($completedSurvey / $registered) * 100, 2) : 0;
        $prayerRate = $completedSurvey > 0 ? round(($completedPrayer / $completedSurvey) * 100, 2) : 0;
        $subscriptionRate = $completedPrayer > 0 ? round(($subscribed / $completedPrayer) * 100, 2) : 0;

        return Inertia::render('Admin/Funnel', [
            'funnel' => [
                'steps' => [
                    [
                        'name' => 'Registro',
                        'count' => $registered,
                        'rate' => 100,
                    ],
                    [
                        'name' => 'Completó Encuesta',
                        'count' => $completedSurvey,
                        'rate' => $surveyRate,
                    ],
                    [
                        'name' => 'Primera Oración',
                        'count' => $completedPrayer,
                        'rate' => $prayerRate,
                    ],
                    [
                        'name' => 'Suscripción Premium',
                        'count' => $subscribed,
                        'rate' => $subscriptionRate,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Lista de usuarios (para tabla de administración)
     */
    public function users(Request $request)
    {
        $query = People::query();

        // Búsqueda
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtros
        if ($request->has('filter')) {
            $filter = $request->input('filter');
            if ($filter === 'admin') {
                $query->where('is_admin', true);
            } elseif ($filter === 'premium') {
                // TODO: Filtrar por usuarios premium cuando se implemente
            } elseif ($filter === 'active') {
                $query->whereHas('respuestas');
            }
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'filters' => $request->only(['search', 'filter']),
        ]);
    }

    /**
     * Crea un nuevo usuario
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'required|email|unique:people,email',
            'pais' => 'nullable|string|max:255',
            'whatsapp' => 'nullable|string|max:20',
            'is_admin' => 'boolean',
        ]);

        People::create($validated);

        return redirect()->back()->with('success', 'Usuario creado correctamente.');
    }

    /**
     * Actualiza un usuario existente
     */
    public function update(Request $request, $id)
    {
        $user = People::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'required|email|unique:people,email,' . $id,
            'pais' => 'nullable|string|max:255',
            'whatsapp' => 'nullable|string|max:20',
            'is_admin' => 'boolean',
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Elimina un usuario
     */
    public function destroy($id)
    {
        $user = People::findOrFail($id);
        $user->delete();

        return redirect()->back()->with('success', 'Usuario eliminado correctamente.');
    }
}
