<?php

namespace App\Http\Controllers;

use App\Http\Repository\Task\CompanyInterface;
use App\Http\Repository\Task\JobSeekerInterface;
use App\Http\Repository\Task\JobVacancyInterface;
use App\Http\Repository\Task\EventInterface;
use App\Imports\ImportSelectionResult;
use App\Models\ApplicantCompany;
use App\Models\Category;
use App\Models\EducationalType;
use App\Models\Experience;
use App\Models\Message;
use App\Models\Province;
use App\Models\Requirement;
use App\Models\SubCategory;
use Carbon\Carbon;
use ErrorException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Mews\Purifier\Facades\Purifier;

class CompanyController extends Controller
{
    protected $companyRepository;
    protected $jobVacancyRepository;
    protected $jobSeekerRepository;
    protected $eventRepository;

    public function __construct(CompanyInterface $companyRepository, JobVacancyInterface $jobVacancyRepository, JobSeekerInterface $jobSeekerRepository, EventInterface $eventRepository)
    {
        $this->jobVacancyRepository = $jobVacancyRepository;
        $this->companyRepository = $companyRepository;
        $this->jobSeekerRepository = $jobSeekerRepository;
        $this->eventRepository = $eventRepository;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($tab)
    {
        if ($tab == 'dashboard') {
            $company = auth()->user()->company;
            return view('company.index', [
                'tab' => $tab,
                'company' => $company,
            ]);
        } else if ($tab == 'profile') {
            $company = auth()->user()->company;
            $provinces = Province::get();
            return view('company.index', [
                'tab' => $tab,
                'company' => $company,
                'provinces' => $provinces,
            ]);
        } else if ($tab == 'jobs-list') {
            $query = request()->input('query');
            $isOpen = request()->input('isOpen');
            $isActive = request()->input('isActive');
            $company = auth()->user()->company;
            if (!$company->isCompleted) {
                return back()->withErrors(['message' => 'Profil Perusahaan belum lengkap, Anda tidak dapat mengakses menu ini']);
            }
            $job_vacancies = $this->jobVacancyRepository->getAllJobVacancyByCompanyId($company->id, $query, $isOpen, $isActive);
            $categories = Category::get();
            $master_requirements = Requirement::get();
            $provinces = Province::get();
            $educational_types = EducationalType::get();
            return view('company.index', [
                'tab' => $tab,
                'company' => $company,
                'job_vacancies' => $job_vacancies,
                'provinces' => $provinces,
                'categories' => $categories,
                'master_requirements' => $master_requirements,
                'educational_types' => $educational_types,
                'query' => $query,
                'isOpen' => $isOpen,
                'isActive' => $isActive,
            ]);
        } else if ($tab == 'events') {
            $company = auth()->user()->company;
            if (!$company->isCompleted) {
                return back()->withErrors(['message' => 'Profil Perusahaan belum lengkap, Anda tidak dapat mengakses menu ini']);
            }
            if (request()->event_code != null) {
                $code = $this->companyRepository->activateEvent(request()->event_code, $company->id);
                if ($code == 200) {
                    createLogUser('Mengikuti bursa kerja');
                    return redirect()->route('companies.index', ['tab' => 'events'])->with('success', 'Berhasil mengikuti bursa kerja');
                } else if ($code == 201) {
                    return redirect()->route('companies.index', ['tab' => 'events'])->withErrors(['message' => 'Anda sudah mengikuti bursa kerja ini']);
                }
                createLogUser('Gagal mengikuti bursa kerja');
                return redirect()->route('companies.index', ['tab' => 'events'])->withErrors(['message' => 'Gagal mengikuti bursa kerja']);
            }
            $query = request()->input('query');
            $isOpen = request()->input('isOpen');
            $events = $this->eventRepository->getAllEventByCompanyId($company->id, $query, $isOpen);
            $categories = Category::get();
            $master_requirements = Requirement::get();
            $educational_types = EducationalType::get();
            return view('company.index', [
                'tab' => 'events',
                'company' => $company,
                'categories' => $categories,
                'master_requirements' => $master_requirements,
                'educational_types' => $educational_types,
                'events' => $events,
                'query' => $query,
                'isOpen' => $isOpen,
            ]);
        }

        return abort(404, 'Halaman tidak ditemukan');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $tab)
    {
        createLogUser('Tambah data ' . $tab);
        if (($tab == 'jobs-list' || $tab == 'job-event-list') && $request->ajax()) {
            $validated = $request->validate([
                'position_name' => ['required', 'string', 'max:255'],
                'requirement' => ['required', 'string'],
                'description' => ['required', 'string'],
                'category_id' => ['required', 'string', 'max:255'],
                'sub_category_id' => ['required', 'string', 'max:255'],
                'salary_from' => ['required', 'string', 'max:20'],
                'salary_to' => ['required', 'string', 'max:20'],
                'total_requirement' => ['required', 'numeric'],
                'brosur' => ['required', 'file'],
            ]);
            if ($tab == 'jobs-list') {
                $validated += $request->validate(['open_until' => ['required', 'date', 'after_or_equal:' . date('m/d/Y')]]);
            }
            if ($request->event_code != null) {
                $event = $this->eventRepository->getEventByCode($request->event_code);
                if (!$event->isSpecialEvent) {
                    $validated += $request->validate([
                        'educational_type_id' => ['nullable', 'numeric'],
                    ]);
                    if ($validated['educational_type_id'] != null) {
                        $validated += $request->validate([
                            'educational_major_id' => ['required', 'numeric'],
                            'graduation_year' => ['required', 'numeric', 'between:1950,' . date('Y')]
                        ]);
                    }
                }
                $validated['event_id'] = $event->id;
                $validated['open_until'] = $event->date_end;
            } else {
                $validated += $request->validate([
                    'educational_type_id' => ['nullable', 'numeric'],
                ]);
                if ($validated['educational_type_id'] != null) {
                    $validated += $request->validate([
                        'educational_major_id' => ['required', 'numeric'],
                        'graduation_year' => ['required', 'numeric', 'between:1950,' . date('Y')]
                    ]);
                }
                $validated['event_id'] = null;
            }
            if (!is_numeric($request->category_id) && SubCategory::find($request->category_id) == null) {
                $category = Category::create(['name' => Purifier::clean($validated['category_id'])]);
                $validated['category_id'] = $category->id;
            }
            if (!is_numeric($request->sub_category_id) && SubCategory::find($request->sub_category_id) == null) {
                $sub_category = SubCategory::create(['category_id' => Purifier::clean($validated['category_id']), 'name' => Purifier::clean($validated['sub_category_id'])]);
                $validated['sub_category_id'] = $sub_category->id;
            }
            $validated['salary_from'] = join(explode('.', str_replace('Rp', '', $request->salary_from)));
            $validated['salary_to'] = join(explode('.', str_replace('Rp', '', $request->salary_to)));
            $validated['master_requirement_id'] = $request->master_requirement_id;

            if ($request->file('brosur') != null) {
                $brosur = $request->file('brosur');
                $brosurPath = $brosur->storeAs('images/company/jobs', md5(rand(), false) . '.' . $brosur->extension());
                $validated['brosur'] = $brosurPath;
            } else {
                $validated['brosur'] = null;
            }
            $sub_category = SubCategory::find($validated['sub_category_id']);
            $validated['slug'] = Str::slug($validated['position_name'] . '-' . Str::random(10));
            $validated['category'] = $sub_category->category->name;
            $validated['sub_category'] = $sub_category->name;
            $validated['company_id'] = auth()->user()->company->id;
            $validated['category_requirement'] = ($request->category_requirement != null ? True : False);
            $validated['isShowSalary'] = ($request->isShowSalary != null ? True : False);
            $validated['isActive'] = ($request->isActive != null ? True : False);
            $validated = Purifier::clean($validated);
            $statusCode = $this->jobVacancyRepository->createJobVacancy($validated);
            if ($statusCode == 200) {
                return [
                    'status' => 200,
                    'message' => 'Berhasil menyimpan data',
                    'event' => $request->event_code != null ? true : false,
                    'event_code' => $request->event_code,
                ];
            }
            if ($statusCode == 201) {
                return [
                    'status' => 201,
                    'message' => 'Berhasil menyimpan Job, requirement tidak tersimpan',
                    'event' => $request->event_code != null ? true : false,
                    'event_code' => $request->event_code,
                ];
            }
            return [
                'status' => 400,
                'message' => 'Gagal menyimpan data',
                'event' => $request->event_code != null ? true : false,
            ];
        } else if ($tab == 'add-applicant' && $request->ajax()) {
            $validated = $request->validate([
                'NIK' => ['required', 'numeric', 'digits_between:16,16'],
                'no_KK' => ['required', 'numeric', 'digits_between:16,16'],
                'fullname' => ['required', 'string', 'max:50'],
                'gender' => ['required', 'string', 'max:15'],
                'place_of_birth' => ['required', 'string', 'max:100'],
                'birthdate' => ['required', 'date'],
                'province_id' => ['required', 'numeric'],
                'city_id' => ['required', 'numeric'],
                'district_id' => ['required', 'numeric'],
                'sub_district_id' => ['required', 'numeric'],
                'address' => ['required', 'string',],
                'RT' => ['required', 'numeric', 'digits_between:1,3'],
                'RW' => ['required', 'numeric', 'digits_between:1,3'],
                'email' => ['required', 'string', 'max:255', 'email'],
            ]);
            $validated['job_vacancy_id'] = $request->job_vacancy_id;
            $validated = Purifier::clean($validated);
            if (ApplicantCompany::create($validated)) {
                return [
                    'status' => 200,
                    'message' => 'Berhasil menyimpan data',
                ];
            }
            return [
                'status' => 400,
                'message' => 'Gagal menyimpan data',
            ];
        }

        return abort(404, 'Halaman tidak ditemukan');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($tab, $id)
    {
        if ($tab == 'jobs-list' && request()->ajax() && request()->suggestion == null) {
            $query = request()->input('query');
            $isOpen = request()->input('isOpen');
            $isActive = request()->input('isActive');
            $job_vacancies = $this->jobVacancyRepository->getAllJobVacancyByCompanyId(auth()->user()->company->id, $query, $isOpen, $isActive);
            return view('components.company.card-job-list', [
                'job_vacancies' => $job_vacancies,
                'query' => $query,
                'isOpen' => $isOpen,
                'isActive' => $isActive,
            ]);
        } else if (($tab == 'jobs-list' || $tab == 'events') && request()->ajax() && (request()->suggestion != null && request()->suggestion != '')) {
            $clients = null;
            $job_vacancy = $this->jobVacancyRepository->getJobVacancyBySlug($id);
            if (($tab == 'jobs-list' || $tab == 'events') && request()->isSpecialEvent == null) {
                $clients = $this->jobSeekerRepository->getJobSeekerRecomendations($job_vacancy->position_name, $job_vacancy->category_id);
            } else if ($tab == 'events' && request()->isSpecialEvent != null) {
                $event = $job_vacancy->event;
                $clients = $this->jobSeekerRepository->getJobSeekerRecomendations($job_vacancy->position_name, $job_vacancy->category_id, $event->institution_id);
            }
            // return $clients;
            return view('components.admin.datatables-client', [
                'clients' => $clients,
            ]);
        }
        return abort(404, 'Halaman tidak ditemukan');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($tab, $id)
    {
        if (($tab == 'jobs-list' || $tab == 'job-event-list') && request()->ajax()) {
            if(request()->uploadFile != null){
                return $this->jobVacancyRepository->getJobVacancyBySlug($id);
            }
            return $this->jobVacancyRepository->getJobVacancyById($id);
        }
        return abort(404, 'Halaman tidak ditemukan');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $tab, $id)
    {
        if ($request->ajax()) {
            createLogUser('Ubah data ' . $tab);

            if ($tab == 'profile') {
                $validated = $request->validate([
                    'company_name' => ['required', 'string', 'max:100'],
                    'address' => ['required', 'string'],
                    'company_branch_address' => ['nullable', 'string'],
                    'phone_number' => ['required', 'numeric', 'digits_between:10,14'],
                    'fax' => ['required', 'string', 'max:100'],
                    'province_id' => ['required', 'numeric'],
                    'city_id' => ['required', 'numeric'],
                    'district_id' => ['required', 'numeric'],
                    'sub_district_id' => ['required', 'numeric'],
                    'business_field' => ['required', 'string', 'max:100'],
                    'NIB' => ['required', 'numeric'],
                    'NIB_date' => ['required', 'date'],
                    'business_license_date' => ['required', 'date'],
                    'total_female_employee' => ['required', 'numeric'],
                    'total_male_employee' => ['required', 'numeric'],
                    'company_status' => ['required', 'string', 'max:255'],
                    'number_of_bpjs' => ['required', 'numeric', 'digits_between:12,13'],
                    'number_of_bpjs_ketnaker' => ['required', 'numeric', 'digits_between:12,13'],
                    'NIK_HRD' => ['sometimes', 'numeric', 'digits_between:16,16'],
                    'nama_HRD' => ['required', 'string', 'max:100'],
                    'gender_HRD' => ['required', 'string', 'max:100'],
                    'leader_name' => ['required', 'string', 'max:100'],
                    'NPWP' => ['required', 'numeric'],
                    'NPWP_name' => ['required', 'string', 'max:100'],
                    'NO_KTP_AN' => ['required', 'numeric', 'digits_between:16,16'],
                    'KTP_AN' => ['required', 'string', 'max:100'],
                    'office_owner' => ['required', 'string', 'max:100'],
                    'scale_of_company' => ['required', 'string', 'max:100'],
                    'logo_company' => ['nullable', 'file'],
                    'facebook' => ['nullable', 'string', 'max:255'],
                    'linkedin' => ['nullable', 'string', 'max:255'],
                    'twitter' => ['nullable', 'string', 'max:255'],
                    'instagram' => ['nullable', 'string', 'max:255'],
                ]);
                $validated['facebook'] = $request->facebook == null ? '#' : $request->facebook;
                $validated['instagram'] = $request->instagram == null ? '#' : $request->instagram;
                $validated['twitter'] = $request->twitter == null ? '#' : $request->twitter;
                $validated['linkedin'] = $request->linkedin == null ? '#' : $request->linkedin;
                if ($request->file('logo_company') != null) {
                    $logo_company = $request->file('logo_company');
                    $logo_companyPath = $logo_company->storeAs('images/company/profile', md5(rand(), false) . '.' . $logo_company->extension());
                    $validated['logo_company'] = $logo_companyPath;
                } else {
                    $validated['logo_company'] = null;
                }
                $validated = Purifier::clean($validated);
                if ($this->companyRepository->updateCompanyById($validated, $id)) {
                    return [
                        'status' => 200,
                        'message' => 'Berhasil menyimpan data'
                    ];
                }
                return [
                    'status' => 400,
                    'message' => 'Gagal menyimpan data'
                ];
            } else if (($tab == 'jobs-list' || $tab == 'events') && ($request->suggestion != null && $request->suggestion != '')) {
                if ($request->idsSeeker == null) {
                    return abort(422, 'Silahkan pilih pencaker yang akan mendapatkan pesan rekomendasi');
                }
                if ($request->phonesSeeker == null) {
                    return abort(422, 'Silahkan pilih pencaker yang akan mendapatkan pesan rekomendasi');
                }
                $job_vacancy = $this->jobVacancyRepository->getJobVacancyBySlug($request->slug);
                if ($job_vacancy->recomendations()->syncWithoutDetaching(explode(',', $request->idsSeeker))) {
                    $message = Message::where('type', 'recomendation job')->first();
                    $finalMessage = str_replace(':position_name', \Str::of($job_vacancy->position_name)->title(), $message->message);
                    $finalMessage = str_replace(':company_name', \Str::of($job_vacancy->company->company_name)->title(), $finalMessage);
                    broadcastMessage($message->id, $finalMessage, null, explode(',', $request->phonesSeeker));
                    return [
                        'status' => 200,
                        'message' => 'Berhasil menyimpan data',
                        'tab' => $tab,
                    ];
                }
                abort(400, 'Gagal menyimpan data');
            } else if ($tab == 'jobs-list' || $tab == 'job-event-list') {
                $validated = $request->validate([
                    'position_name' => ['required', 'string', 'max:255'],
                    'requirement' => ['required', 'string'],
                    'description' => ['required', 'string'],
                    'category_id' => ['required', 'string', 'max:255'],
                    'sub_category_id' => ['required', 'string', 'max:255'],
                    'salary_from' => ['required', 'string', 'max:20'],
                    'salary_to' => ['required', 'string', 'max:20'],
                    'total_requirement' => ['required', 'numeric'],
                    'brosur' => ['nullable', 'file'],
                ]);
                $validated['salary_from'] = join(explode('.', str_replace('Rp', '', $request->salary_from)));
                $validated['salary_to'] = join(explode('.', str_replace('Rp', '', $request->salary_to)));
                $validated['master_requirement_id'] = $request->master_requirement_id;
                if ($tab == 'jobs-list') {
                    $validated += $request->validate(['open_until' => ['required', 'date', 'after_or_equal:' . date('m/d/Y')]]);
                }
                $validated += $request->validate([
                    'educational_type_id' => ['nullable', 'numeric'],
                ]);
                if ($validated['educational_type_id'] != null) {
                    $validated += $request->validate([
                        'educational_major_id' => ['required', 'numeric'],
                        'graduation_year' => ['required', 'numeric', 'between:1950,' . date('Y')]
                    ]);
                }
                if (!is_numeric($request->category_id) && SubCategory::find($request->category_id) == null) {
                    $category = Category::create(['name' => Purifier::clean($validated['category_id'])]);
                    $validated['category_id'] = $category->id;
                }
                if (!is_numeric($request->sub_category_id) && SubCategory::find($request->sub_category_id) == null) {
                    $sub_category = SubCategory::create(['category_id' => Purifier::clean($validated['category_id']), 'name' => Purifier::clean($validated['sub_category_id'])]);
                    $validated['sub_category_id'] = $sub_category->id;
                }
                if ($request->file('brosur') != null) {
                    $brosur = $request->file('brosur');
                    $brosurPath = $brosur->storeAs('images/company/jobs', md5(rand(), false) . '.' . $brosur->extension());
                    $validated['brosur'] = $brosurPath;
                } else {
                    $validated['brosur'] = null;
                }
                $sub_category = SubCategory::find($validated['sub_category_id']);
                $validated['category'] = $sub_category->category->name;
                $validated['sub_category'] = $sub_category->name;
                $validated['category_requirement'] = ($request->category_requirement != null ? True : False);
                $validated['isShowSalary'] = ($request->isShowSalary != null ? True : False);
                $validated['isActive'] = ($request->isActive != null ? True : False);
                $validated = Purifier::clean($validated);
                $statusCode = $this->jobVacancyRepository->updateJobVacancyById($validated, $id);
                if ($statusCode == 200) {
                    return [
                        'status' => 200,
                        'message' => 'Berhasil menyimpan data',
                        'event' => $request->event_code != null ? true : false,
                        'event_code' => $request->event_code,
                    ];
                }
                if ($statusCode == 201) {
                    return [
                        'status' => 201,
                        'message' => 'Berhasil menyimpan Job, requirement tidak tersimpan',
                        'event' => $request->event_code != null ? true : false,
                        'event_code' => $request->event_code,
                    ];
                }
                return [
                    'status' => 400,
                    'message' => 'Gagal menyimpan data',
                    'event' => $request->event_code != null ? true : false,
                ];
            }
        }
        return abort(404, 'Halaman tidak ditemukan');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tab, $id)
    {
        createLogUser('Hapus data ' . $tab);

        if (($tab == 'jobs-list' || $tab == 'job-event-list') && request()->ajax()) {
            if ($this->jobVacancyRepository->deleteJobVacancyById($id)) {
                return [
                    'status' => 200,
                    'message' => 'Berhasil menghapus data',
                    'tab' => $tab,
                ];
            }
            return [
                'status' => 400,
                'message' => 'Gagal menghapus data',
                'tab' => $tab,
            ];
        }
        return abort(404, 'Halaman tidak ditemukan');
    }

    public function getJobEventList(Request $request, $id)
    {
        if ($request->ajax()) {
            $query = $request->input('query');
            $isOpen = $request->input('isOpen');
            $isActive = $request->input('isActive');
            $company = auth()->user()->company;
            $event = $this->eventRepository->getEventByCode($id);
            $job_vacancies = $this->jobVacancyRepository->getAllJobVacancyByEventId($company->id, $event->id, $query, $isOpen, $isActive);
            if ($request->tab != null) {
                return view('components.company.card-job-event-list', [
                    'company' => $company,
                    'job_vacancies' => $job_vacancies,
                    'query' => $query,
                    'isOpen' => $isOpen,
                    'isActive' => $isActive,
                    'id' => $id,
                    'event' => $event,
                ]);
            }
            return view('components.company.job-event-list', [
                'company' => $company,
                'job_vacancies' => $job_vacancies,
                'query' => $query,
                'isOpen' => $isOpen,
                'isActive' => $isActive,
                'id' => $id,
                'event' => $event,
            ]);
        }
        return abort(404, 'Halaman tidak ditemukan');
    }

    public function getListApplicants($id)
    {
        if (request()->ajax()) {
            $query = request()->input('query');
            $status =  request()->input('status');
            $isCanAddApplicant = true;
            $applicants = $this->jobSeekerRepository->getSeekersByJobId($id, $query, $status);
            $applicant_companies = ApplicantCompany::where('job_vacancy_id', $id)->when($query != null, function ($q) use ($query) {
                $q->where('fullname', 'LIKE', '%' . $query . '%');
            })->when($status != null, function ($qr) use ($status) {
                $qr->where('status', $status);
            })->orderBy('updated_at', 'DESC')->paginate(10);
            return view('components.company.list-applicants', compact('applicants', 'query', 'status', 'id', 'isCanAddApplicant', 'applicant_companies'));
        }
        return abort(404, 'Halaman tidak ditemukan');
    }

    public function getExternalApplicants($id)
    {
        if (request()->ajax()) {
            $query = request()->input('query');
            $status =  request()->input('status');
            $isCanAddApplicant = true;
            $applicant_companies = ApplicantCompany::where('job_vacancy_id', $id)->when($query != null, function ($q) use ($query) {
                $q->where('fullname', 'LIKE', '%' . $query . '%');
            })->when($status != null, function ($qr) use ($status) {
                $qr->where('status', $status);
            })->orderBy('updated_at', 'DESC')->paginate(10);
            return view('components.company.external-applicants', compact('query', 'status', 'id', 'isCanAddApplicant', 'applicant_companies'));
        }
        return abort(404, 'Halaman tidak ditemukan');
    }

    public function getEventListApplicants($id)
    {
        if (request()->ajax()) {
            $query = request()->input('query');
            $status =  request()->input('status');
            $applicants = $this->jobSeekerRepository->getSeekersByJobId($id, $query, $status);
            return view('components.company.event-list-applicants', compact('applicants', 'query', 'status', 'id'));
        }
        return abort(404, 'Halaman tidak ditemukan');
    }

    public function realtimeDashboard()
    {
        $id = auth()->user()->company->id;
        $counts = $this->companyRepository->getCompanyWithJobEventById($id);
        $applicants = $this->jobVacancyRepository->getApplicantsByCompanyId($id);
        $total_applicants = 0;
        foreach ($counts->job_vacancies as $item) {
            $total_applicants += $item->applicants_count;
        }
        return view('components.company.part-dashboard', [
            'counts' => $counts,
            'total_applicants' => $total_applicants,
            'applicants' => $applicants,
        ]);
    }

    public function getCVApplicants($slug)
    {
        createLogUser('Download CV');
        $client = $this->jobSeekerRepository->getJobSeekerBySlug($slug);
        $file = public_path('storage/' . $client->file_cv);
        $headers = array(
            'Content-Type: application/pdf',
        );

        return response()->download($file, 'CV_' . $client->fullname . '.pdf', $headers);
    }

    public function updateApplicant($slug_seeker, $job_id)
    {
        if (request()->ajax()) {
            createLogUser('Ubah Status Pelamar');
            $status = request()->input('status');
            $status = $status == 'pending' ? 'process' : Purifier::clean($status);
            if (request()->tab == 'external') {
                if (ApplicantCompany::where('id', $slug_seeker)->where('job_vacancy_id', $job_id)->update(['status' => $status])) {
                    return [
                        'status' => 200,
                        'message' => 'Berhasil mengubah status',
                    ];
                }
                return [
                    'status' => 400,
                    'message' => 'Gagal mengubah status',
                ];
            }
            $seeker = $this->jobSeekerRepository->getJobSeekerBySlug($slug_seeker);
            if ($seeker->applicants()->updateExistingPivot($job_id, ['status' => $status])) {
                $position_name = $this->jobVacancyRepository->getJobVacancyById($job_id)->position_name;
                $message = Message::where('type', 'status pelamar')->first();
                $finalMessage = str_replace(':job_vacancy', Str::of($position_name)->title(), $message->message);
                $finalMessage = str_replace(':status', Str::of($status)->title(), $message->message);
                broadcastMessage($message->id, $finalMessage, $seeker->phone_number, null);
                return [
                    'status' => 200,
                    'message' => 'Berhasil mengubah status',
                ];
            }
            return [
                'status' => 400,
                'message' => 'Gagal mengubah status',
            ];
        }
        return abort(404, 'Halaman tidak ditemukan');
    }

    public function getSuggestion()
    {
        if (request()->ajax()) {

            return view('components.company.suggestion');
        }
        return abort(404, 'Halaman tidak ditemukan');
    }

    public function updateSelectionApplicant($slug)
    {
        request()->validate([
            'importSelectionFile' => 'required|mimes:xls,xlsx'
        ]);
        $job_vacancy = $this->jobVacancyRepository->getJobVacancyBySlug($slug);
        $onlyExternal = false;
        $withExternal = false;
        if (!ApplicantCompany::where('job_vacancy_id', $job_vacancy->id)->get()->isEmpty()) {
            $withExternal = true;
        }
        if ($job_vacancy->applicants->isEmpty()) {
            if ($withExternal) {
                $onlyExternal = true;
            } else {
                abort(422, 'Lowongan ini belum ada pelamar dari Website Hallo Kerja');
            }
        }
        try {
            $file = request()->file('importSelectionFile');
            $data = Excel::toArray(new ImportSelectionResult(), $file)[0];
            if (empty($data)) {
                return abort(422, 'Tidak ada data di excel');
            } else {
                $mapping = array_map(function ($item) {
                    if ($item[2] != null) {
                        return $item[2];
                    }
                }, $data);
                if ($withExternal) {
                    ApplicantCompany::where('job_vacancy_id', $job_vacancy->id)->whereIn('NIK', $mapping)->update(['status' => 'accepted']);
                    ApplicantCompany::where('job_vacancy_id', $job_vacancy->id)->whereNotIn('NIK', $mapping)->update(['status' => 'rejected']);
                }
                if(!$onlyExternal){
                    if (($job_seekersIn = $this->jobSeekerRepository->getJobSeekerByNIKs($mapping))) {
                        $ids = [];
                        $experiences = [];
                        foreach ($job_seekersIn as $value) {
                            array_push($ids, $value->id);
                            array_push($experiences, [
                                'job_seeker_id' => $value->id,
                                'company_name' => $job_vacancy->company->company_name,
                                'category_id' => $job_vacancy->category_id,
                                'sub_category_id' => $job_vacancy->sub_category_id,
                                'province_id' => $job_vacancy->company->province_id,
                                'city_id' => $job_vacancy->company->city_id,
                                'district_id' => $job_vacancy->company->district_id,
                                'sub_district_id' => $job_vacancy->company->sub_district_id,
                                'start_year' => date('Y-m-d'),
                                'isStillWork' => True,
                                'end_year' => null,
                                'file' => null,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                        }
                        $job_vacancy->applicants()->updateExistingPivot($ids, ['status' => 'accepted']);
                        Experience::whereIn('id', $ids)->update(['isStillWork' => False]);
                        Experience::insert($experiences);
                    } else {
                        return abort(422, 'Tidak ada data di excel');
                    }
                    if (($job_seekersNotIn = $this->jobSeekerRepository->getJobSeekerByNotNIKs($mapping))) {
                        $ids = [];
                        foreach ($job_seekersNotIn as $value) {
                            array_push($ids, $value->id);
                        }
                        $job_vacancy->applicants()->updateExistingPivot($ids, ['status' => 'rejected']);
                    }
                }
                $filePath = $file->storeAs('lowongan/file/' . $job_vacancy->company->slug, md5(rand(), false) . '.' . $file->extension());
                if (file_exists(public_path('storage/' . $job_vacancy->selection_file) && $job_vacancy->selection_file != null)) {
                    unlink(public_path('storage/' . $job_vacancy->selection_file));
                }
                $job_vacancy->update(['isActive' => false, 'selection_file' => $filePath]);
                return [
                    'success' => 200,
                    'message' => 'Berhasil Import Data. Lowongan akan otomatis tidak Aktif',
                ];
            }
        } catch (ErrorException $th) {
            return back()->withErrors(['message' => 'Terjadi kesalahan saat import. Pastikan format excel sesuai dengan template!.']);
        }
        return back()->with('success', 'Berhasil mengimport data');
    }

    public function downloadTemplate()
    {
        if (file_exists(public_path('storage/template/template_seleksi.xlsx'))) {
            return response()->download(public_path('storage/template/template_seleksi.xlsx'));
        }
    }
}
