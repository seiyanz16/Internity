<?php

namespace App\Http\Controllers\Api;

// use ZipArchive;
use Barryvdh\DomPDF\Facade\Pdf as DomPdfFacade;
use mikehaertl\pdftk\Pdf;
use Carbon\Carbon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function pdfSingleCompany(Request $request, $id)
    {
        $user = auth()->user();
        $courseName = $user->courses->first()?->name;
        $courseLevel = $courseName ? explode(' ', $courseName)[0] : 'N/A';

        $formattedDateOfBirth = $user->date_of_birth
        ? Carbon::parse($user->date_of_birth)->translatedFormat('j F Y')
        : 'N/A';

        $gender = match ($user->gender) {
            'male' => 'Laki-Laki',
            'female' => 'Perempuan'
        };

        $companyId = $id;
        $company = $user->companies()->find($companyId);
        if (!$company) {
            return response()->json(['message' => 'Company not found!'], 404);
        }

        $internDate = $user->internDates()->where('company_id', $companyId)->first();

        $startDate = $internDate ? Carbon::parse($internDate->start_date)->translatedFormat('j F Y') : 'N/A';
        $endDate = $internDate ? Carbon::parse($internDate->end_date)->translatedFormat('j F Y') : 'N/A';
    
        $presences = $user->presences()
            ->where('company_id', $companyId)
            ->whereDate('date', '<=', Carbon::now())
            ->orderBy('date', 'asc')
            ->get()
            ->filter(fn($presence) => !is_null($presence->check_in))
            ->values();

        if ($presences->isEmpty()) {
            return response()->json(['message' => 'No presences found!'], 404);
        }

        $journals = $user->journals()
            ->where('company_id', $companyId)
            ->whereDate('date', '<=', Carbon::now())
            ->orderBy('date', 'asc')
            ->get()
            ->filter(fn($journal) => !is_null($journal->work_type))
            ->values();
        
        if ($journals->isEmpty()) {
            return response()->json(['message' => 'No journals found!'], 404);
        }

        $parentAddress =  $request->input('parent_address') ?? $user->address;
        $directors = $request->input('directors', []);
        $hrds = $request->input('hrds', []);
        $schoolMentors = $request->input('school_mentors', []);
        $companyMentors = $request->input('company_mentors', []);

        $formFields = [
           'namasiswa' => $user->name,
            'kelas' => $courseLevel,
            'nis' => $user->nis,
            'nis_nisn' => $user->nis . '/' . $request->input('nisn'),
            'gol_darah' => $request->input('blood_type'),
            'alamat_siswa' => $user->address,
            'jurusan' => $user->departments->first()?->description,
            'kelasjurusan' => $courseName,
            'ttl' => $formattedDateOfBirth,
            'gender' => $gender,
            'namaortu' =>  $request->input('parent_name'),
            'alamat_ortu' => $parentAddress,
            'tgl_export' => Carbon::now()->translatedFormat('l, j F Y'),
            'instansi_nama' => $company->name,
            'instansi_bidang' => $company->category,
            'instansi_alamat' => $company->address,
            'instansi_tglmulai' => $startDate,
            'instansi_tglselesai' => $endDate,
            'instansi_direktur' => $directors[0]['name'] ?? 'N/A',
            'instansi_hrd' => $hrds[0]['name'] ?? 'N/A',
            'instansi_psekolah' => $schoolMentors[0]['name'] ?? 'N/A',
            'instansi_piduka' => $companyMentors[0]['name'] ?? 'N/A',
        ];

        foreach ($presences as $index => $presence) {
            $i = $index + 1;
            $formFields["tanggal$i"] = Carbon::parse($presence->date)->translatedFormat('l, j F Y');
            $formFields["checkin$i"] = $presence->check_in;
            $formFields["checkout$i"] = $presence->check_out ?? 'N/A';
        }

        foreach ($journals as $index => $journal) {
            $i = $index + 1;
            $formFields["no$i"] = $i;
            $formFields["bidang$i"] = $journal->work_type;
            $formFields["uraian$i"] = $journal->description;
            $formFields["tgl_jurnal$i"] = Carbon::parse($journal->date)->translatedFormat('l, j F Y');
        }

        $templatePath = '';
        if (count($presences) <= 93 || count($journals) <= 93) {
            $templatePath = public_path('template-pdf/template_3_month.pdf');
        } else {
            $templatePath = public_path('template-pdf/template_6_month.pdf');
        }

        if (!file_exists($templatePath)) {
            return response()->json(['message' => 'PDF template not found!'], 404);
        }

        $fileName = $user->id . time() . '.pdf';
        $tempPath = storage_path('storage/journals/' . $fileName);
        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;
        $pdf = new Pdf($templatePath);
        $result = $pdf->fillForm($formFields)->flatten()->saveAs($outputPath);
        if (!$result) {
            $error = $pdf->getError();
            return response()->json(['message' => 'Failed to generate PDF: ' . $error], 500);
        }

        return response()->download($outputPath, $fileName)->deleteFileAfterSend(true);
    }

    public function pdfMultipleCompany(Request $request)
    {
        $user = auth()->user();
        $courseName = $user->courses->first()?->name;
        $courseLevel = $courseName ? explode(' ', $courseName)[0] : 'N/A';
    
        $formattedDateOfBirth = $user->date_of_birth
            ? Carbon::parse($user->date_of_birth)->translatedFormat('j F Y')
            : 'N/A';
    
        $gender = match ($user->gender) {
            'male' => 'Laki-Laki',
            'female' => 'Perempuan'
        };
    
        $parentAddress =  $request->input('parent_address') ?? $user->address;
    
        $companies = $user->companies;
    
        if ($companies->isEmpty()) {
            return response()->json(['message' => 'No companies associated with the user!'], 404);
        }
    
        $formFields = [
            'namasiswa' => $user->name,
            'kelas' => $courseLevel,
            'nis' => $user->nis,
            'nis_nisn' => $user->nis . '/' . $request->input('nisn'),
            'gol_darah' => $request->input('blood_type'),
            'alamat_siswa' => $user->address,
            'jurusan' => $user->departments->first()?->description,
            'kelasjurusan' => $courseName,
            'ttl' => $formattedDateOfBirth,
            'gender' => $gender,
            'namaortu' =>  $request->input('parent_name'),
            'alamat_ortu' => $parentAddress,
            'tgl_export' => Carbon::now()->translatedFormat('l, j F Y'),
        ];

        $directors = $request->input('directors', []);
        $hrds = $request->input('hrds', []);
        $school_mentors = $request->input('school_mentors', []);
        $company_mentors = $request->input('company_mentors', []);
    
        foreach ($companies as $index => $company) {
            $companyIndex = $index + 1;
            
            $formFields["instansi{$companyIndex}_nama"] = $company->name;
            $formFields["instansi{$companyIndex}_bidang"] = $company->category;
            $formFields["instansi{$companyIndex}_alamat"] = $company->address;
            $formFields["instansi{$companyIndex}_hrd"] = $company->contact_person;
            $formFields["instansi{$companyIndex}_tglmulai"] = Carbon::parse($company->internDates->first()->start_date)->translatedFormat('j F Y');
            $formFields["instansi{$companyIndex}_tglselesai"] = Carbon::parse($company->internDates->first()->end_date)->translatedFormat('j F Y');
            $formFields["instansi{$companyIndex}_direktur"] = $directors[$index]['name'] ?? 'N/A';
            $formFields["instansi{$companyIndex}_hrd"] = $hrds[$index]['name'] ?? 'N/A';
            $formFields["instansi{$companyIndex}_psekolah"] = $school_mentors[$index]['name'] ?? 'N/A';
            $formFields["instansi{$companyIndex}_piduka"] = $company_mentors[$index]['name'] ?? 'N/A';

            $presences = $user->presences()
                ->where('company_id', $company->id)
                ->whereDate('date', '<=', Carbon::now())
                ->orderBy('date', 'asc')
                ->get()
                ->filter(fn($presence) => !is_null($presence->check_in))
                ->values();
            
            foreach ($presences as $presenceIndex => $presence) {
                $formFields["{$companyIndex}_tanggal" . ($presenceIndex + 1)] = Carbon::parse($presence->date)->translatedFormat('l, j F Y');
                $formFields["{$companyIndex}_checkin" . ($presenceIndex + 1)] = $presence->check_in;
                $formFields["{$companyIndex}_checkout" . ($presenceIndex + 1)] = $presence->check_out ?? 'N/A';
            }
            
            $journals = $user->journals()
                ->where('company_id', $company->id)
                ->whereDate('date', '<=', Carbon::now())
                ->orderBy('date', 'asc')
                ->get()
                ->filter(fn($journal) => !is_null($journal->work_type))
                ->values();
            
            foreach ($journals as $journalIndex => $journal) {
                $formFields["{$companyIndex}_no" . ($journalIndex + 1)] = $journalIndex + 1;
                $formFields["{$companyIndex}_bidang" . ($journalIndex + 1)] = $journal->work_type;
                $formFields["{$companyIndex}_uraian" . ($journalIndex + 1)] = $journal->description;
                $formFields["{$companyIndex}_tgl_jurnal" . ($journalIndex + 1)] = Carbon::parse($journal->date)->translatedFormat('l, j F Y');
            }
        }
    
        $templatePath = public_path('template-pdf/template_2-company.pdf');
        if (!file_exists($templatePath)) {
            return response()->json(['message' => 'PDF template not found!'], 404);
        }
    
        $fileName = $user->id . time() . '.pdf';
        $tempPath = storage_path('storage/journals/' . $fileName);
        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;
        $pdf = new Pdf($templatePath);
        $result = $pdf->fillForm($formFields)->flatten()->saveAs($outputPath);
        if (!$result) {
            $error = $pdf->getError();
            return response()->json(['message' => 'Failed to generate PDF: ' . $error], 500);
        }
    
        return response()->download($outputPath, $fileName)->deleteFileAfterSend(true);
    }
    public function exportCertificate(Request $request, $id)
    {
        $user = auth()->user();
        $company = $user->companies()->find($id);

        if (!$company) {
            return response()->json(['message' => 'Company not found!'], 404);
        }

        $internDate = $user->internDates()->where('company_id', $id)->first();
        $scores = $user->scores()->where('company_id', $id)->get();
        $noSertif = $request->input('no_sertif');

        if ($scores->isEmpty()) {
            return response()->json(['message' => 'Scores not found! Call the mentor to add scores first!'], 400);
        }

        $averageScore = $scores->avg('score') ?? 0;
        // $technicalScore = $scores->where('type', 'teknis')->avg('score') ?? 0;
        // $nonTechnicalScore = $scores->where('type', 'non-teknis')->avg('score') ?? 0;

        $formattedDateOfBirth = $user->date_of_birth
            ? Carbon::parse($user->date_of_birth)->translatedFormat('j F Y')
            : 'N/A';

        $formFields = [
            'no_sertif' => 'No. ' . $noSertif,
            'nama_siswa' => $user->name,
            'ttl' => $formattedDateOfBirth,
            'nis' => $user->nis,
            'program_studi' => $user->departments->first()?->study_program,
            'jurusan' => $user->departments->first()?->description,
            'instansi_nama' => $company->name,
            'tgl_mulai' => $internDate ? Carbon::parse($internDate->start_date)->translatedFormat('j F Y') : 'N/A',
            'tgl_selesai' => $internDate ? Carbon::parse($internDate->end_date)->translatedFormat('j F Y') : 'N/A',
            'tgl_export' => Carbon::now()->translatedFormat('j F Y'),
            'instansi_direktur' => $company->contact_person,
            'nilai_all' => number_format($averageScore, 2),
        ];

        $templatePath = public_path('template-pdf/template_certificate_infront.pdf');

        if (!file_exists($templatePath)) {
            return response()->json(['message' => 'PDF template not found!'], 404);
        }

        $fileName = $user->id . '_certificate_' . time() . '.pdf';
        $outputPathFront = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'front_' . $fileName;
        $pdfFront = new Pdf($templatePath);
        $resultFront = $pdfFront->fillForm($formFields)->flatten()->saveAs($outputPathFront);

        if (!$resultFront) {
            return response()->json(['message' => 'Failed to generate front page PDF: ' . $pdfFront->getError()], 500);
        }

        // Generate halaman belakang (back) dengan DomPDF
        $technicalScores = $scores->where('type', 'teknis');
        $nonTechnicalScores = $scores->where('type', 'non-teknis');

        foreach ($technicalScores as $score) {
            $score->letter = $this->convertScoreToLetter($score->score);
        }
        foreach ($nonTechnicalScores as $score) {
            $score->letter = $this->convertScoreToLetter($score->score);
        }

        $pdfBack = DomPdfFacade::loadView('pdf.certificate_back', [
            'technicalScores' => $technicalScores,
            'nonTechnicalScores' => $nonTechnicalScores,
            'avgScore' => number_format($averageScore, 2),
        ])->setPaper('a4', 'landscape');

        $outputPathBack = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'back_' . $fileName;
        $pdfBack->save($outputPathBack);

        // Gabungkan PDF depan & belakang
        $mergedPdfPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;
        $pdf = new Pdf();
        $mergeResult = $pdf->addFile($outputPathFront)
            ->addFile($outputPathBack)
            ->saveAs($mergedPdfPath);

        if (!$mergeResult) {
            return response()->json(['message' => 'Failed to merge PDF files: ' . $pdf->getError()], 500);
        }

        return response()->download($mergedPdfPath, $fileName)->deleteFileAfterSend(true);
    }

    private function convertScoreToLetter($score)
    {
        if ($score >= 90) {
            return 'Sangat Baik';
        } elseif ($score >= 75) {
            return 'Baik';
        } elseif ($score >= 60) {
            return 'Cukup';
        } else {
            return 'Kurang';
        }
    }
}
