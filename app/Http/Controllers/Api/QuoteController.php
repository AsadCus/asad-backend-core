<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    private array $quotes = [
        'en' => [
            [
                'quote' => 'Small steps every day add up to big results.',
                'name' => 'Anonymous',
                'title' => 'Motivation',
            ],
            [
                'quote' => 'Done is better than perfect.',
                'name' => 'Anonymous',
                'title' => 'Motivation',
            ],
            [
                'quote' => 'Focus on progress, not perfection.',
                'name' => 'Anonymous',
                'title' => 'Motivation',
            ],
            [
                'quote' => 'Your future is created by what you do today.',
                'name' => 'Anonymous',
                'title' => 'Motivation',
            ],
            [
                'quote' => 'Discipline beats motivation.',
                'name' => 'Anonymous',
                'title' => 'Motivation',
            ],
            [
                'quote' => 'Make today count.',
                'name' => 'Anonymous',
                'title' => 'Motivation',
            ],
            [
                'quote' => 'Great work starts with showing up.',
                'name' => 'Anonymous',
                'title' => 'Motivation',
            ],
        ],
        'id' => [
            [
                'quote' => 'Langkah kecil setiap hari membuahkan hasil besar.',
                'name' => 'Anonymous',
                'title' => 'Motivasi',
            ],
            [
                'quote' => 'Selesai lebih baik daripada sempurna.',
                'name' => 'Anonymous',
                'title' => 'Motivasi',
            ],
            [
                'quote' => 'Fokus pada kemajuan, bukan kesempurnaan.',
                'name' => 'Anonymous',
                'title' => 'Motivasi',
            ],
            [
                'quote' => 'Masa depanmu ditentukan oleh apa yang kamu lakukan hari ini.',
                'name' => 'Anonymous',
                'title' => 'Motivasi',
            ],
            [
                'quote' => 'Disiplin mengalahkan motivasi.',
                'name' => 'Anonymous',
                'title' => 'Motivasi',
            ],
            [
                'quote' => 'Buat hari ini berarti.',
                'name' => 'Anonymous',
                'title' => 'Motivasi',
            ],
            [
                'quote' => 'Kerja hebat dimulai dengan hadir.',
                'name' => 'Anonymous',
                'title' => 'Motivasi',
            ],
        ],
    ];

    public function random(Request $request): JsonResponse
    {
        $lang = $request->query('lang', 'id');
        $quotes = $this->quotes[$lang] ?? $this->quotes['id'];

        $today = date('z');
        $quoteIndex = $today % count($quotes);

        return response()->json($quotes[$quoteIndex]);
    }
}
