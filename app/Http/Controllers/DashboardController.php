<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Candidat;
use App\Models\Offre;
use App\Models\Interview;
use App\Models\User;

class DashboardController extends Controller
{
    // Stats pour Admin
    public function getAdminStats()
    {
        $totalCandidats = Candidat::count();
        $totalOffres = Offre::count();
        $totalEntretiens = Interview::count();
        $totalRecruteurs = User::where('role', 'recruteur')->count();
        
        // Tendances des derniers 7 jours
        $candidatsTendance = $this->getTendance('candidats');
        $offresTendance = $this->getTendance('offres');
        $entretiensTendance = $this->getTendance('interviews');
        
        return response()->json([
            'totalCandidats' => $totalCandidats,
            'totalOffres' => $totalOffres,
            'totalEntretiens' => $totalEntretiens,
            'totalRecruteurs' => $totalRecruteurs,
            'candidatsTendance' => $candidatsTendance,
            'offresTendance' => $offresTendance,
            'entretiensTendance' => $entretiensTendance
        ]);
    }
    
    public function getCandidatsParDepartement()
    {
        $data = DB::table('candidats')
            ->join('offres', 'candidats.offre_id', '=', 'offres.id')
            ->select('offres.departement', DB::raw('count(*) as total'))
            ->groupBy('offres.departement')
            ->get();
            
        return response()->json($data);
    }
    
    public function getCandidatsParMois()
    {
        $data = DB::table('candidats')
            ->select(
                DB::raw('MONTH(created_at) as mois'),
                DB::raw('YEAR(created_at) as annee'),
                DB::raw('count(*) as total')
            )
            ->whereYear('created_at', date('Y'))
            ->groupBy('mois', 'annee')
            ->orderBy('annee')
            ->orderBy('mois')
            ->get()
            ->map(function ($item) {
                $moisNoms = [
                    1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr',
                    5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août',
                    9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'
                ];
                
                return [
                    'name' => $moisNoms[$item->mois],
                    'Candidats' => $item->total
                ];
            });
            
        return response()->json($data);
    }
    
    public function getOffresParDepartement()
    {
        $data = DB::table('offres')
            ->select('departement', DB::raw('count(*) as total'))
            ->groupBy('departement')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->departement,
                    'value' => $item->total
                ];
            });
            
        return response()->json($data);
    }
    
    public function getEntretiensParStatut()
    {
        $data = DB::table('interviews')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->status,
                    'value' => $item->total
                ];
            });
            
        return response()->json($data);
    }
    
    public function getCandidatsParNiveau()
    {
        $data = DB::table('candidats')
            ->select('niveauEtude', DB::raw('count(*) as total'))
            ->groupBy('niveauEtude')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->niveauEtude,
                    'value' => $item->total
                ];
            });
            
        return response()->json($data);
    }
    
    // Stats pour Recruteur
    public function getRecruteurStats(Request $request)
    {
        $recruteurId = $request->user()->id;
        
        $totalMesCandidats = DB::table('candidats')
            ->join('offres', 'candidats.offre_id', '=', 'offres.id')
            ->where('offres.responsabilite', $recruteurId)
            ->count();
            
        $totalMesOffres = Offre::where('responsabilite', $recruteurId)->count();
        
        $totalMesEntretiens = DB::table('interviews')
            ->where('recruteur_id', $recruteurId)
            ->count();
            
        $entretiensPending = DB::table('interviews')
            ->where('recruteur_id', $recruteurId)
            ->where('status', 'pending')
            ->count();
            
        // Tendances des derniers 7 jours pour ce recruteur
        $candidatsTendance = $this->getTendanceRecruteur('candidats', $recruteurId);
        $entretiensTendance = $this->getTendanceRecruteur('interviews', $recruteurId);
        
        return response()->json([
            'totalMesCandidats' => $totalMesCandidats,
            'totalMesOffres' => $totalMesOffres,
            'totalMesEntretiens' => $totalMesEntretiens,
            'entretiensPending' => $entretiensPending,
            'candidatsTendance' => $candidatsTendance,
            'entretiensTendance' => $entretiensTendance
        ]);
    }
    
    public function getMesOffres(Request $request)
    {
        $recruteurId = $request->user()->id;
        
        $data = Offre::where('responsabilite', $recruteurId)
            ->select('poste', 'dateExpiration', 'id')
            ->withCount('candidats')
            ->get()
            ->map(function ($offre) {
                return [
                    'name' => $offre->poste,
                    'value' => $offre->candidats_count,
                    'id' => $offre->id,
                    'expiration' => $offre->dateExpiration
                ];
            });
            
        return response()->json($data);
    }
    
    public function getMesEntretiens(Request $request)
    {
        $recruteurId = $request->user()->id;
        
        $data = DB::table('interviews')
            ->where('recruteur_id', $recruteurId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->status,
                    'value' => $item->total
                ];
            });
            
        return response()->json($data);
    }
    
    public function getCandidatsParOffre(Request $request)
    {
        $recruteurId = $request->user()->id;
        
        $data = DB::table('candidats')
            ->join('offres', 'candidats.offre_id', '=', 'offres.id')
            ->where('offres.responsabilite', $recruteurId)
            ->select('offres.poste', DB::raw('count(*) as total'))
            ->groupBy('offres.poste')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->poste,
                    'value' => $item->total
                ];
            });
            
        return response()->json($data);
    }
    
    public function getEntretiensParJour(Request $request)
    {
        $recruteurId = $request->user()->id;
        $startDate = Carbon::now()->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();
        
        $data = DB::table('interviews')
            ->where('recruteur_id', $recruteurId)
            ->whereBetween('date_heure', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(date_heure) as jour'),
                DB::raw('count(*) as total')
            )
            ->groupBy('jour')
            ->get()
            ->map(function ($item) {
                $joursSemaine = [
                    0 => 'Dim', 1 => 'Lun', 2 => 'Mar', 3 => 'Mer',
                    4 => 'Jeu', 5 => 'Ven', 6 => 'Sam'
                ];
                
                $date = Carbon::parse($item->jour);
                
                return [
                    'name' => $joursSemaine[$date->dayOfWeek],
                    'value' => $item->total
                ];
            });
            
        return response()->json($data);
    }
    
    // Méthodes utilitaires
    private function getTendance($table)
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();
        
        $data = DB::table($table)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as jour'),
                DB::raw('count(*) as total')
            )
            ->groupBy('jour')
            ->get()
            ->map(function ($item) {
                $joursSemaine = [
                    0 => 'Dim', 1 => 'Lun', 2 => 'Mar', 3 => 'Mer',
                    4 => 'Jeu', 5 => 'Ven', 6 => 'Sam'
                ];
                
                $date = Carbon::parse($item->jour);
                
                return [
                    'name' => $joursSemaine[$date->dayOfWeek],
                    'value' => $item->total
                ];
            });
            
        return $data;
    }
    
    private function getTendanceRecruteur($table, $recruteurId)
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();
        
        if ($table === 'candidats') {
            $data = DB::table('candidats')
                ->join('offres', 'candidats.offre_id', '=', 'offres.id')
                ->where('offres.responsabilite', $recruteurId)
                ->whereBetween('candidats.created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(candidats.created_at) as jour'),
                    DB::raw('count(*) as total')
                )
                ->groupBy('jour')
                ->get();
        } else {
            $data = DB::table($table)
                ->where('recruteur_id', $recruteurId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(created_at) as jour'),
                    DB::raw('count(*) as total')
                )
                ->groupBy('jour')
                ->get();
        }
        
        return $data->map(function ($item) {
            $joursSemaine = [
                0 => 'Dim', 1 => 'Lun', 2 => 'Mar', 3 => 'Mer',
                4 => 'Jeu', 5 => 'Ven', 6 => 'Sam'
            ];
            
            $date = Carbon::parse($item->jour);
            
            return [
                'name' => $joursSemaine[$date->dayOfWeek],
                'value' => $item->total
            ];
        });
    }
}