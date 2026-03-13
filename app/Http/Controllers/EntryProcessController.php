<?php

namespace App\Http\Controllers;

set_time_limit(300); // Increase execution time to 300 seconds

use App\Mail\AdvancePayment;
use App\Mail\AssignmentNotificationMail;
use App\Mail\ClientEmail;
use App\Mail\CorrectionNotificationMail;
use App\Mail\FinalPayment;
use App\Mail\ManagerNotificationMail;
use App\Mail\PaperStatisticsMail;
use App\Mail\PartialPayment;
use App\Mail\ProjectPaymentStatusEmail;
use App\Mail\ProjectStatusEmail;
use App\Mail\SampleSizeMail;
use App\Mail\TaskCompleteEmail;
use App\Mail\TaskEmail;
use App\Mail\ThesisReviewingMail;
use App\Mail\ThesisWithMsMail;
use App\Mail\ThesisWithoutTextMail;
use App\Mail\ThesisWithTextMail;
use App\Mail\WritingMail;
use App\Mail\WritingWithStatisticsMail;
use App\Models\Activity;
use App\Models\ActivityDocuments;
use App\Models\AssigneeStatus;
use App\Models\Commends;
use App\Models\DepartmentModel;
use App\Models\EmployeePaymentDetails;
use App\Models\EntryDocument;
use App\Models\EntryDocumentsList;
use App\Models\EntryProcessModel;
use App\Models\InstitutionModel;
use App\Models\MailNotification;
use App\Models\PaymentDetails;
use App\Models\PaymentLogs;
use App\Models\PaymentStatusModel;
use App\Models\PendingStatusModel;
use App\Models\People;
use App\Models\ProfessionModel;
use App\Models\ProjectActivity;
use App\Models\ProjectAssignDetails;
use App\Models\ProjectLogs;
use App\Models\ProjectStatus;
use App\Models\ProjectViewStatus;
use App\Models\Roles;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use SebastianBergmann\CodeCoverage\Report\Xml\Project;

use function PHPSTORM_META\type;

class EntryProcessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $start_date = $request->query('start_date');
        $end_date = $request->query('end_date');
        $type_of_work = $request->query('type_of_work') ?? 'all';
        $institutions = $request->query('institutions') ?? 'all';
        $process_status = $request->query('process_status');
        // $authorname = $request->query('author_name') ?? 'all';
        // $details = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'client_name', 'process_status', DB::raw("CONCAT(DATEDIFF(projectduration, created_at), ' days ', MOD(TIMESTAMPDIFF(HOUR, created_at, projectduration), 24), ' hrs') AS projectduration"))->with(['paymentProcess', 'institute', 'department', 'profession'])->where('is_deleted', 0);
        $details = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'client_name', 'process_status', DB::raw("CONCAT(DATEDIFF(projectduration, entry_date), ' days') AS projectduration"))
        // DB::raw("CONCAT(DATEDIFF(projectduration, entry_date), ' days ', MOD(TIMESTAMPDIFF(HOUR, entry_date, projectduration), 24), ' hrs') AS projectduration"))
            ->with(['paymentProcess', 'institute', 'department', 'profession'])->where('is_deleted', 0);
        if (isset($start_date) && isset($end_date)) {
            $details = $details->whereBetween('entry_date', [$start_date, $end_date]);
        }

        if (isset($type_of_work) && $type_of_work != 'all') {
            $details = $details->where('type_of_work', $type_of_work);
        }
        if (isset($institutions) && $institutions != 'all') {
            $details = $details->where('institute', $institutions);
        }
        if (isset($process_status) && $process_status != 'all') {
            $details = $details->where('process_status', $process_status);
        }

        // if ($authorname !== 'all') {
        //     $details = $details->where(function ($query) use ($authorname) {
        //         // Check if any of the four columns match the given author name
        //         $query->orWhere('writer', '=', $authorname)
        //             ->orWhere('reviewer', '=', $authorname)
        //             ->orWhere('statistican', '=', $authorname)
        //             ->orWhere('journal', '=', $authorname);
        //     });
        // }

        $details = $details->orderBy('id', 'desc')->get();

        foreach ($details as $item) {
            // Use EntryProcessModel.project_id for tracking
            $item->tracking_status = $this->getTrackingStatusByProjectId($item->id);
        }

        // foreach ($details as $item) {
        //     if ($item->projectduration) {
        //         $projectDate = Carbon::parse($item->projectduration);
        //         $createdDate = Carbon::parse($item->created_at);
        //         $diff = $createdDate->diff($projectDate);
        //         $item->duration_diff = $diff->format('%a days %h hours');
        //     } else {
        //         $item->duration_diff = null;
        //     }
        // }

        $typeofwork = EntryProcessModel::where('is_deleted', 0)
            ->select('type_of_work')
            ->orderBy('created_at', 'desc')
            ->get();

        $institutionsList = InstitutionModel::where('is_deleted', 0)
            ->where('status', 'Active')
            ->select('name', 'id')
            ->get();
        $authornameList = User::with(['createdByUser'])
            ->whereIn('position', [7, 8, 10, 11])
            ->select('id', 'employee_name', 'profile_image', 'position', 'employee_type')
            ->groupBy('id')
            ->get();
        $processStatus = EntryProcessModel::where('is_deleted', 0)
            ->select('process_status')
            ->distinct()
            ->get();

        return response()->json([
            'details' => $details,
            'typeofwork' => $typeofwork,
            'institutions' => $institutionsList,
            'authorname' => $authornameList,
            'processstatus' => $processStatus,
            'ragul' => "ragul"
        ]);
    }

    public function indexPub(Request $request)
    {
        $start_date = $request->query('start_date');
        $end_date = $request->query('end_date');
        $type_of_work = $request->query('type_of_work') ?? 'all';
        $institutions = $request->query('institutions') ?? 'all';
        $process_status = $request->query('process_status');
        // $authorname = $request->query('author_name') ?? 'all';
        // $details = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'client_name', 'process_status', DB::raw("CONCAT(DATEDIFF(projectduration, created_at), ' days ', MOD(TIMESTAMPDIFF(HOUR, created_at, projectduration), 24), ' hrs') AS projectduration"))->with(['paymentProcess', 'institute', 'department', 'profession'])->where('is_deleted', 0)->where('type_of_work','manuscript');
        $details = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'client_name', 'process_status', DB::raw("CONCAT(DATEDIFF(projectduration, entry_date), ' days') AS projectduration"))
        // DB::raw("CONCAT(DATEDIFF(projectduration, entry_date), ' days ', MOD(TIMESTAMPDIFF(HOUR, entry_date, projectduration), 24), ' hrs') AS projectduration"))
            ->with(['paymentProcess', 'institute', 'department', 'profession'])->where('is_deleted', 0)
            ->whereHas('journalData', function ($query) {
                $query->where('type', 'publication_manager');
            })
            ->where('type_of_work', 'manuscript');
        if (isset($start_date) && isset($end_date)) {
            $details = $details->whereBetween('entry_date', [$start_date, $end_date]);
        }

        if (isset($type_of_work) && $type_of_work != 'all') {
            $details = $details->where('type_of_work', $type_of_work);
        }
        if (isset($institutions) && $institutions != 'all') {
            $details = $details->where('institute', $institutions);
        }
        if (isset($process_status) && $process_status != 'all') {
            $details = $details->where('process_status', $process_status);
        }

        // if ($authorname !== 'all') {
        //     $details = $details->where(function ($query) use ($authorname) {
        //         // Check if any of the four columns match the given author name
        //         $query->orWhere('writer', '=', $authorname)
        //             ->orWhere('reviewer', '=', $authorname)
        //             ->orWhere('statistican', '=', $authorname)
        //             ->orWhere('journal', '=', $authorname);
        //     });
        // }

        $details = $details->orderBy('id', 'desc')->get();

        foreach ($details as $item) {
            // Use EntryProcessModel.project_id for tracking
            $item->tracking_status = $this->getTrackingStatusByProjectId($item->id);
        }

        // foreach ($details as $item) {
        //     if ($item->projectduration) {
        //         $projectDate = Carbon::parse($item->projectduration);
        //         $createdDate = Carbon::parse($item->created_at);
        //         $diff = $createdDate->diff($projectDate);
        //         $item->duration_diff = $diff->format('%a days %h hours');
        //     } else {
        //         $item->duration_diff = null;
        //     }
        // }

        $typeofwork = EntryProcessModel::where('is_deleted', 0)
            ->select('type_of_work')
            ->orderBy('created_at', 'desc')
            ->get();

        $institutionsList = InstitutionModel::where('is_deleted', 0)
            ->where('status', 'Active')
            ->select('name', 'id')
            ->get();
        $authornameList = User::with(['createdByUser'])
            ->whereIn('position', [7, 8, 10, 11])
            ->select('id', 'employee_name', 'profile_image', 'position', 'employee_type')
            ->groupBy('id')
            ->get();
        $processStatus = EntryProcessModel::where('is_deleted', 0)
            ->select('process_status')
            ->distinct()
            ->get();

        return response()->json([
            'details' => $details,
            'typeofwork' => $typeofwork,
            'institutions' => $institutionsList,
            'authorname' => $authornameList,
            'processstatus' => $processStatus,
        ]);
    }

    public function findPhoneNumber(Request $request)
    {
        $phone = $request->query('phone');
        $email = $request->query('email');

        try {
            if ($phone && strlen($phone) > 1) {
                $phoneDetails = EntryProcessModel::with(['institute', 'department', 'profession'])
                    ->where('contact_number', $phone)
                    ->select('id', 'email', 'client_name', 'contact_number', 'institute', 'department', 'profession')
                    ->first();
            } elseif ($email && strlen($email) > 1) {
                $phoneDetails = EntryProcessModel::with(['institute', 'department', 'profession'])
                    ->where('email', $email)
                    ->select('id', 'email', 'client_name', 'contact_number', 'institute', 'department', 'profession')
                    ->first();
            }

            return response()->json([
                'details' => $phoneDetails,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in findPhoneNumber: '.$e->getMessage());

            return response()->json(['error' => 'Something went wrong'], 500);
        }
    }

    private function getTrackingStatusByProjectId($id)
    {
        $tracking = null;

        $peopleIds_pm = People::where('position', '27')->pluck('id')->toArray();
        $peopleIds_sme = People::where('position', '28')->pluck('id')->toArray();

        $notAssigned = EntryProcessModel::where('id', $id)
            ->where('process_status', 'not_assigned')
            ->exists();
        $inProgress = EntryProcessModel::where('id', $id)
            ->where('process_status', 'in_progress')
            ->exists();
        $withdrawal = EntryProcessModel::where('id', $id)
            ->where('process_status', 'withdrawal')
            ->exists();

        // Active assignments
        $writer = ProjectAssignDetails::where('project_id', $id)
            ->where('type', 'writer')
            ->whereIn('status', ['to_do', 'on_going', 'correction', 'rejected', 'plag_correction'])
            ->whereHas('projectData', fn ($q) => $q->where('process_status', '!=', 'completed'))
            ->exists();

        $reviewer = ProjectAssignDetails::where('project_id', $id)
            ->where('type', 'reviewer')
            ->whereIn('status', ['to_do', 'on_going', 'correction', 'rejected', 'plag_correction'])
            ->whereHas('projectData', fn ($q) => $q->where('process_status', '!=', 'completed'))
            ->exists();

        $writerRejected = ProjectAssignDetails::where('project_id', $id)
            ->where('type', 'writer')
            ->where('status', 'rejected')
            ->whereHas('projectData', fn ($q) => $q->where('process_status', '!=', 'completed'))
            ->exists();

        $reviewerRejected = ProjectAssignDetails::where('project_id', $id)
            ->where('type', 'reviewer')
            ->where('status', 'rejected')
            ->whereHas('projectData', fn ($q) => $q->where('process_status', '!=', 'completed'))
            ->exists();

        $statistician = ProjectAssignDetails::where('project_id', $id)
            ->where('type', 'statistican')
            ->whereIn('status', ['to_do', 'on_going', 'correction', 'rejected', 'plag_correction'])
            ->whereHas('projectData', fn ($q) => $q->where('process_status', '!=', 'completed'))
            ->exists();

        $rejected = ProjectAssignDetails::where('project_id', $id)
            ->where('status', 'rejected')
            ->whereHas('projectData', fn ($q) => $q->where('process_status', '!=', 'completed'))
            ->exists();

        $correction = ProjectAssignDetails::where('project_id', $id)
            ->where('type', 'team_coordinator')
            ->whereIn('type_sme', ['writer', 'Publication Manager', 'reviewer', '2nd_writer'])
            ->whereHas('projectData', fn ($q) => $q->where('process_status', '!=', 'completed'))
            ->exists();

        // SME / Completion checks
        $writerCompleted = ProjectAssignDetails::where('project_id', $id)
            ->where('type', 'writer')
            ->whereIn('status', ['need_support', 'completed'])
            ->exists();

        $reviewerCompleted = ProjectAssignDetails::where('project_id', $id)
            ->where('type', 'reviewer')
            ->whereIn('status', ['need_support', 'completed'])
            ->exists();
        $statisticianCompleted = ProjectAssignDetails::where('project_id', $id)
            ->where('type', 'statistican')
            ->whereIn('status', ['need_support', 'completed'])
            ->exists();

        $smePublicationCompleted = ProjectAssignDetails::where('project_id', $id)
            ->whereIn('created_by', $peopleIds_pm)
            ->where('type', 'publication_manager')
            ->whereIn('status', [
                'pending_author', 'rejected', 'reviewer_comments',
                'resubmission', 'published', 'submitted',
            ])
            ->exists();

        $publicationCompleted = ProjectAssignDetails::where('project_id', $id)
            ->whereIn('created_by', $peopleIds_sme)
            ->where('type', 'publication_manager')
            ->whereIn('status', [
                'pending_author', 'rejected', 'reviewer_comments',
                'resubmission', 'published', 'submitted',
            ])
            ->exists();

        /** -------- FINAL DECISION ORDER -------- */
        if ($notAssigned) {
            $tracking = 'Project Manager';

        } elseif ($withdrawal) {
            $tracking = 'Project Withdrawn';
        } elseif ($rejected && $writer && $reviewer && $statistician) {
            $tracking = 'TC, Writer, Reviewer, Statistician';

        } elseif ($rejected === true && $reviewerRejected === false) {
            $tracking = 'Project Manager, TC';
        } elseif ($rejected && $writerRejected) {
            $tracking = 'Project Manager, TC, Reviewer';
        } elseif ($rejected && $reviewerRejected) {
            $tracking = 'Project Manager, TC, writer';

        } elseif ($writer && $reviewer && $statistician) {
            $tracking = 'Writer, Reviewer, Statistician';

        } elseif ($writer && $reviewer) {
            $tracking = 'Writer, Reviewer';
        } elseif ($reviewer) {
            $tracking = 'Reviewer';
        } elseif ($writer) {
            $tracking = 'Writer';

        } elseif ($correction) {
            $tracking = 'TC';
        } elseif ($inProgress) {
            $tracking = 'TC';
        } elseif ($writer && $statistician) {

            $tracking = 'Writer, Statistician';

        } elseif ($reviewer && $statistician) {
            $tracking = 'Reviewer, Statistician';
        } elseif (($writerCompleted && $reviewerCompleted) || $smePublicationCompleted || $writerCompleted || $reviewerCompleted || $statisticianCompleted) {
            $tracking = 'SME';

        } elseif ($publicationCompleted) {
            $tracking = 'Publication Manager';

        } elseif ($writer) {
            $tracking = 'Writer';

        } elseif ($reviewer) {
            $tracking = 'Reviewer';

        } elseif ($statistician) {
            $tracking = 'Statistician';

        } else {
            $tracking = 'Project Manager';
        }

        return $tracking;
    }

    public function getEmployeeName()
    {
        // Define mapping of position numbers to role names
        $roleMapping = [
            '7' => 'writer',
            '8' => 'reviewer',
            '11' => 'statistican',
            '27' => 'journal',
        ];

        try {
            // Query the database to fetch employees with specified positions
            $employees = DB::connection('mysql_medics_hrms')
                ->table('employee_details')
                ->where('status', '1')
                ->whereIn('position', array_keys($roleMapping))
                ->get();

            $result = [];

            foreach ($employees as $employee) {
                // Convert position to an array (in case of multiple values)
                $posarray = explode(',', $employee->position);

                foreach ($posarray as $postition) {
                    // Check if position exists in mapping
                    if (isset($roleMapping[$postition])) {
                        $roleName = $roleMapping[$postition]; // Get the role name

                        // Store employee details under the corresponding role
                        $result[$roleName][] = [
                            'id' => $employee->id,
                            'name' => $employee->employee_name,
                            'employeeType' => $employee->employee_type,
                        ];
                    }
                }
            }

            // Return result as JSON response
            return response()->json($result);
        } catch (\Exception $e) {
            // Log the error and return a response if the query fails
            Log::error($e->getMessage());

            return response()->json(['error' => 'Unable to fetch data'], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'entryprocess_documents.*.file.*' => 'file|max:204800', // 200MB per file
        ]);
        Log::info('File uploaded successfully:', $request->all());
        Log::info('Processing payment detail:', $request->payment_details);
        $selectedOption = $request->type_of_work;
        $customId = $request->project_id;
        // $invoiceNumber = EntryProcessModel::generateInvoiceNumber();

        try {
            DB::transaction(function () use ($request, &$customId, &$details) {
                $invoiceNumber = EntryProcessModel::generateInvoiceNumber();

                // Create new entry
                $details = new EntryProcessModel;
                $details->entry_date = $request->entry_date ?? null;
                $details->title = $request->title ?? null;
                $details->project_id = $customId;
                $details->type_of_work = $request->type_of_work ?? null;
                $details->others = $request->others ?? null;
                $details->client_name = $request->client_name ?? null;
                $details->email = $request->email ?? null;
                $details->contact_number = $request->contact_number ?? null;
                $details->institute = $request->institute ?? null;
                $details->department = $request->department ?? null;
                $details->profession = $request->profession ?? null;
                $details->budget = $request->budget ?? null;
                $details->process_status = $request->process_status ?? 'not_assigned';
                $details->process_date = $request->process_date ?? null;
                $details->hierarchy_level = $request->hierarchy_level ?? null;
                $details->else_project_manager = $request->else_project_manager;
                $details->comment_box = $request->comment_box ?? null;
                $details->status = $request->status ?? '1';
                $details->is_deleted = $request->is_deleted ?? 0;
                $details->created_by = $request->created_by ?? '-';
                $details->projectduration = $request->project_duration;
                $details->invoice_number = $invoiceNumber;

                $details->save();

                $created = User::with('createdByUser')->find($request->created_by);

                $employee = $created?->employee_name ?? 'Mohamed Ali';
                $creator = $created?->createdByUser?->name ?? 'Admin';

                $byText = $creator ? " by {$employee} ({$creator})" : " by {$employee}";

                $activity = new ProjectActivity;
                $activity->project_id = $details->id;
                $activity->activity = "Project created {$byText}";
                $activity->role = $creator;
                $activity->created_by = $request->created_by;
                $activity->created_date = now();
                $activity->save();

                //payment update
                // if (! is_null($request->payment_status) && trim($request->payment_status) !== '') {

                //     $details_p = new PaymentStatusModel;
                //     $details_p->project_id = $details->id;
                //     $details_p->payment_status = $request->payment_status;
                //     $details_p->reference_number = $request->reference_number;
                //     $details_p->discounts = $request->discount;
                //     $details_p->created_by = $request->created_by;

                //     if ($request->hasFile('reference_number_file')) {

                //         $files = [];
                //         $path = public_path('payment_screenshots');

                //         if (! is_dir($path)) {
                //             mkdir($path, 0775, true);
                //         }

                //         foreach ($request->file('reference_number_file') as $file) {
                //             $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
                //                         .'_'.time().'_'.uniqid()
                //                         .'.'.$file->extension();

                //             $file->move($path, $filename);
                //             $files[] = $filename;
                //         }

                //         // store as JSON
                //         $details_p->reference_number_file = json_encode($files);
                //     }
                //     $details_p->save();

                //     if ($request->has('payment_details') && is_array($request->payment_details)) {
                //         foreach ($request->payment_details as $pay) {

                //             // Add each payment detail
                //             $paymentDetails = new PaymentDetails;
                //             $paymentDetails->payment_id = $details_p->id;
                //             $paymentDetails->payment = ! empty($pay['amount']) ? $pay['amount'] : '0';
                //             $paymentDetails->payment_type = $request->payment_status;
                //             $paymentDetail->reference_number = $request->reference_number ?? '';
                //             $paymentDetail->reference_number_file = json_encode($files);
                //             $paymentDetails->payment_date = ! empty($pay['date']) ? $pay['date'] : now();
                //             $paymentDetails->save();

                //             // $activity = new ProjectActivity;
                //             // $activity->project_id = $details->id;
                //             // $activity->activity = 'Payment marked as '.$request->payment_status;
                //             // $activity->created_by = $request->created_by;
                //             // $activity->created_date = now();
                //             // $activity->save();

                //             $created = User::with('createdByUser')->find($request->created_by);

                //             $employee = $created?->employee_name ?? 'Mohamed Ali';
                //             $creator = $created?->createdByUser?->name ?? 'Admin';

                //             $activityText = "Payment marked as {$request->payment_status} by {$employee} ({$creator})";

                //             $activity = new ProjectActivity;
                //             $activity->project_id = $details->id;
                //             $activity->activity = $activityText;
                //             $activity->role = $creator;
                //             $activity->created_by = $request->created_by;
                //             $activity->created_date = now();
                //             $activity->save();
                //         }
                //     }

                //     if (! empty($request->payment_status)) {
                //         PaymentLogs::create([
                //             'project_id' => $details->id,
                //             'payment_id' => $details_p->id,
                //             'payment_status' => $request->payment_status,
                //             'created_by' => $request->created_by,
                //             'created_date' => date('Y-m-d H:i:s'),
                //         ]);
                //     }

                //     // if ($request->created_by != '86') {
                //     //     $activity = new ProjectActivity;
                //     //     $activity->project_id = $request->created_by;
                //     //     $activity->activity = 'Payment marked as ' . $request->payment_status;
                //     //     $activity->created_by = $request->created_by;
                //     //     $activity->created_date = now();
                //     //     $activity->save();
                //     // }
                // }

                // if (! is_null($request->payment_status) && trim($request->payment_status) !== '') {

                //     $details_p = new PaymentStatusModel;
                //     $details_p->project_id = $details->id;
                //     $details_p->payment_status = $request->payment_status;
                //     $details_p->reference_number = $request->reference_number;
                //     $details_p->discounts = $request->discount;
                //     $details_p->created_by = $request->created_by;

                //     $files = [];

                //     if ($request->hasFile('reference_number_file')) {

                //         $path = public_path('payment_screenshots');

                //         if (! is_dir($path)) {
                //             mkdir($path, 0775, true);
                //         }

                //         foreach ($request->file('reference_number_file') as $file) {
                //             $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
                //                 .'_'.time().'_'.uniqid()
                //                 .'.'.$file->extension();

                //             $file->move($path, $filename);
                //             $files[] = $filename;
                //         }

                //         $details_p->reference_number_file = json_encode($files);
                //     }

                //     $details_p->save();

                //     if ($request->has('payment_details') && is_array($request->payment_details)) {
                //         foreach ($request->payment_details as $pay) {

                //             $paymentDetails = new PaymentDetails;
                //             $paymentDetails->payment_id = $details_p->id;
                //             $paymentDetails->payment = ! empty($pay['amount']) ? $pay['amount'] : '0';
                //             $paymentDetails->payment_type = $request->payment_status;
                //             $paymentDetails->reference_number = $request->reference_number ?? '';
                //             $paymentDetails->reference_number_file = ! empty($files) ? json_encode($files) : null;
                //             $paymentDetails->payment_date = ! empty($pay['date']) ? $pay['date'] : now();
                //             $paymentDetails->save();

                //             $created = User::with('createdByUser')->find($request->created_by);

                //             $employee = $created?->employee_name ?? 'Mohamed Ali';
                //             $creator = $created?->createdByUser?->name ?? 'Admin';

                //             $activityText = "Payment marked as {$request->payment_status} by {$employee} ({$creator})";

                //             $activity = new ProjectActivity;
                //             $activity->project_id = $details->id;
                //             $activity->activity = $activityText;
                //             $activity->role = $creator;
                //             $activity->created_by = $request->created_by;
                //             $activity->created_date = now();
                //             $activity->save();
                //         }
                //     }

                //     if (! empty($request->payment_status)) {
                //         PaymentLogs::create([
                //             'project_id' => $details->id,
                //             'payment_id' => $details_p->id,
                //             'payment_status' => $request->payment_status,
                //             'created_by' => $request->created_by,
                //             'created_date' => now(),
                //         ]);
                //     }
                // }

                if (! is_null($request->payment_status) && trim($request->payment_status) !== '') {
                    // 1. Create main payment record
                    $details_p = new PaymentStatusModel;
                    $details_p->project_id = $details->id;
                    $details_p->payment_status = $request->payment_status;
                    $details_p->reference_number = $request->reference_number;
                    $details_p->discounts = $request->discount;
                    $details_p->created_by = $request->created_by;

                    $mainFiles = [];

                    // Handle main payment files
                    if ($request->hasFile('reference_number_file')) {
                        $path = public_path('payment_screenshots');

                        if (! is_dir($path)) {
                            mkdir($path, 0775, true);
                        }

                        foreach ($request->file('reference_number_file') as $file) {
                            $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
                                .'_'.time().'_'.uniqid()
                                .'.'.$file->extension();

                            $file->move($path, $filename);
                            $mainFiles[] = $filename;
                        }

                        // $details_p->reference_number_file = ! empty($mainFiles) ? json_encode($mainFiles) : null;
                        $details_p->reference_number_file = $mainFiles ?: null;
                    }

                    $details_p->save();

                    if ($request->has('payment_details') && is_array($request->payment_details)) {
                        foreach ($request->payment_details as $index => $pay) {
                            $paymentDetails = new PaymentDetails;
                            $paymentDetails->payment_id = $details_p->id;

                            $paymentDetails->payment = ! empty($pay['payment']) ? $pay['payment'] : '0';

                            $paymentDetails->payment_type = ! empty($pay['payment_type']) ? $pay['payment_type'] : $request->payment_status;

                            $paymentDetails->reference_number = ! empty($pay['reference_number']) ? $pay['reference_number'] : ($request->reference_number ?? '');

                            $paymentDetails->payment_date = ! empty($pay['payment_date']) ? $pay['payment_date'] : now();

                            $detailFiles = [];

                            if ($request->hasFile("payment_details.{$index}.reference_number_file")) {
                                $detailFileInput = $request->file("payment_details.{$index}.reference_number_file");

                                $detailFilesToProcess = is_array($detailFileInput) ? $detailFileInput : [$detailFileInput];

                                foreach ($detailFilesToProcess as $file) {
                                    if ($file && $file->isValid()) {
                                        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
                                            .'_'.time().'_'.uniqid().'_detail'
                                            .'.'.$file->extension();

                                        $path = public_path('payment_screenshots');

                                        if (! is_dir($path)) {
                                            mkdir($path, 0775, true);
                                        }

                                        $file->move($path, $filename);
                                        $detailFiles[] = $filename;
                                    }
                                }

                                // $paymentDetails->reference_number_file = ! empty($detailFiles) ? json_encode($detailFiles) : null;
                                $paymentDetails->reference_number_file = $detailFiles ?: null;
                            }

                            $paymentDetails->save();

                            $created = User::with('createdByUser')->find($request->created_by);
                            $employee = $created?->employee_name ?? 'Mohamed Ali';
                            $creator = $created?->createdByUser?->name ?? 'Admin';

                            $paymentTypeForActivity = ! empty($pay['payment_type']) ? $pay['payment_type'] : $request->payment_status;
                            $activityText = "Payment marked as {$paymentTypeForActivity} by {$employee} ({$creator})";

                            $activity = new ProjectActivity;
                            $activity->project_id = $details->id;
                            $activity->activity = $activityText;
                            $activity->role = $creator;
                            $activity->created_by = $request->created_by;
                            $activity->created_date = now();
                            $activity->save();
                        }
                    }
                    if (! empty($request->payment_status)) {
                        PaymentLogs::create([
                            'project_id' => $details->id,
                            'payment_id' => $details_p->id,
                            'payment_status' => $request->payment_status,
                            'reference_number' => $request->reference_number,
                            'reference_number_file' => $details_p->reference_number_file,
                            'created_by' => $request->created_by,
                            'created_date' => now(),
                        ]);
                    }
                }

                if ($request->has('payment_freelancer') && is_array($request->payment_freelancer)) {
                    foreach ($request->payment_freelancer as $pay) {

                        $paymentDetails = new EmployeePaymentDetails;
                        $paymentDetails->project_id = $details->id;
                        $paymentDetails->payment_id = $details_p->id;
                        $paymentDetails->employee_id = $pay['employee_id'] ?? null;
                        $paymentDetails->payment = $pay['payment'] ?? null;
                        $paymentDetails->status = $pay['status'] ?? null;
                        $paymentDetails->payment_date = $pay['date'] ?? now();
                        $paymentDetails->type = $pay['type'] ?? '';
                        $paymentDetails->created_date = $pay['date'] ?? now();

                        $paymentDetails->save();
                    }
                }

                //payment end

                if ($details->process_status === 'not_assigned') {
                    $activity = new ProjectActivity;
                    $activity->project_id = $details->id;
                    $activity->activity = "Assigned to PM by {$employee} {$creator}";
                    $activity->role = $creator;
                    $activity->created_by = $request->created_by;
                    $activity->created_date = date('Y-m-d H:i:s');
                    $activity->save();
                    $role = '85';
                    $userDetails = User::with('createdByUser')->find($role);
                    $created = User::with(['createdByUser'])->find($request->created_by);

                    try {
                        Mail::to($userDetails->email_address)->send(new ManagerNotificationMail([
                            'name' => $userDetails->employee_name,
                            'role' => 'Project Manager',
                            'project_id' => $customId,
                            'title' => $request->title,
                            'duration' => $request->projectduration,
                            'created_by' => $created?->employee_name ?? 'Mohamed Ali',
                            'created_by_role' => $created?->createdByUser?->name ?? 'Admin',
                            // 'unit'       => $details->$durationUnit ?? null, // prevent undefined property error
                        ]));
                        sleep(5);

                        Log::info("Email sent to writer ({$userDetails->email_address}).");
                    } catch (\Exception $e) {
                        Log::error('Failed to send email to writer: '.$e->getMessage());
                    }
                } else {
                    $activity = new ProjectActivity;
                    $activity->project_id = $details->id;
                    $activity->activity = "Assigned to TC by {$employee} {$creator}";
                    $activity->role = $creator;
                    $activity->created_by = $request->created_by;
                    $activity->created_date = date('Y-m-d H:i:s');
                    $activity->save();

                    $role = '86';
                    $userDetails = User::with('createdByUser')->find($role);
                    $created = User::with(['createdByUser'])->find($request->created_by);

                    try {
                        Mail::to($userDetails->email_address)->send(new ManagerNotificationMail([
                            'name' => $userDetails->employee_name,
                            'role' => 'Team Coordinator',
                            'project_id' => $customId,
                            'title' => $request->title,
                            'duration' => $request->projectduration,
                            'created_by' => $created?->employee_name ?? 'Mohamed Ali',
                            'created_by_role' => $created?->createdByUser?->name ?? 'Admin',
                            // 'unit'       => $details->$durationUnit ?? null, // prevent undefined property error
                        ]));
                        sleep(5);

                        Log::info("Email sent to writer ({$userDetails->email_address}).");
                    } catch (\Exception $e) {
                        Log::error('Failed to send email to writer: '.$e->getMessage());
                    }
                }

                if (! empty($request->process_status)) {
                    $comments = new ProjectViewStatus;

                    $comments->project_id = $details->id;
                    $comments->project_status = $request->process_status;
                    $comments->created_by = $request->created_by;
                    $comments->created_date = date('Y-m-d H:i:s');
                    $comments->save();
                }

                if (! empty($request->comment_box)) {
                    $comments = new Commends;

                    $comments->project_id = $details->id;
                    $comments->commend_box = $request->comment_box ?? null;
                    $comments->created_by = $request->created_by;
                    $comments->assignee = $request->created_by;
                    $comments->created_date = date('Y-m-d H:i:s');
                    $comments->save();
                }

                // Log::info('check $request->writer', $request->writer)

                // Project Logs
                if (is_array($request->writer) && ! empty($request->writer)) {
                    foreach ($request->writer as $user) {

                        $assigned_d = new ProjectAssignDetails;
                        $assigned_d->project_id = $details->id;
                        $assigned_d->assign_user = $user['writer'];
                        $assigned_d->assign_date = $user['writerDate'];
                        $assigned_d->status = $user['writerStatus'];
                        $assigned_d->status_date = $user['writerStatusDate'];
                        $assigned_d->project_duration = $user['writerprojectduration'];
                        $assigned_d->comments = $user['writer_comment'] ?? '';
                        $assigned_d->type = 'writer';
                        $assigned_d->created_by = $request->created_by;
                        $assigned_d->save();

                        $userDetails = User::find($user['writer']);
                        Log::info('check $userDetails', ['userDetails' => $userDetails]);
                        $created = User::find($request->created_by);
                        Log::info('check $created', ['created' => $created]);
                        $creator = $created?->createdByUser?->name ?? 'Admin';
                        $createdName = $created?->employee_name ?? 'Mohamed Ali';

                        $activity = new ProjectActivity;
                        $activity->project_id = $details->id;
                        $activity->activity = 'Project assigned to '.$userDetails->employee_name.' (writer)';
                        $activity->role = $creator;
                        $activity->created_by = $request->created_by;
                        $activity->created_date = now();
                        $activity->save();

                        if ($userDetails) {
                            try {
                                Log::info('Preparing to send email', [
                                    'to' => $userDetails->email_address,
                                    'created_by_name' => $created->employee_name,
                                    'created_by_id' => $request->created_by,
                                ]);
                                Mail::to($userDetails->email_address)->queue(new AssignmentNotificationMail([
                                    'name' => $userDetails->employee_name,
                                    'role' => 'writer',
                                    'project_id' => $customId,
                                    'title' => $request->title,
                                    'duration' => $request->projectduration,
                                    'createdBy' => $created->employee_name,
                                    'created_by_role' => $creator,
                                ]));

                                Log::info("Email successfully queued to employee name ({$created->employee_name}).");
                                Log::info("Email successfully queued to writer ({$userDetails->email_address}).");
                            } catch (\Exception $e) {
                                Log::error('Failed to send email to writer: '.$e->getMessage(), [
                                    'exception' => $e,
                                    'trace' => $e->getTraceAsString(),
                                ]);
                            }
                        } else {
                            Log::warning("User not found for writer ID: {$user['writer']}");
                        }

                        ProjectStatus::create([
                            'project_id' => $details->id,
                            'assign_id' => $user['writer'],
                        ]);

                        ProjectLogs::create([
                            'project_id' => $details->id,
                            'employee_id' => $user['writer'],
                            'assigned_date' => $user['writerDate'],
                            'status' => $user['writerStatus'],
                            'status_date' => $user['writerStatusDate'],
                            'status_type' => 'writer',
                            'created_by' => $request->created_by,
                            'created_date' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                if (is_array($request->reviewer) && ! empty($request->reviewer)) {
                    foreach ($request->reviewer as $user) {

                        $assigned_d = new ProjectAssignDetails;

                        $assigned_d->project_id = $details->id;
                        $assigned_d->assign_user = $user['reviewer'];
                        $assigned_d->assign_date = $user['reviewerAssignedDate'];
                        $assigned_d->status = $user['reviewerStatus'];
                        $assigned_d->status_date = $user['reviewerStatusDate'];
                        $assigned_d->project_duration = $user['reviewerProjectDuration'];
                        $assigned_d->comments = $user['reviewer_comment'] ?? '';
                        $assigned_d->type = 'reviewer';
                        $assigned_d->created_by = $request->created_by;
                        $assigned_d->save();

                        $userDetails = User::find($user['reviewer']);
                        $created = User::with(['createdByUser'])->find($request->created_by);
                        $creator = $created?->createdByUser?->name ?? 'Admin';
                        $activity = new ProjectActivity;
                        $activity->project_id = $details->id;
                        $activity->activity = 'Project assigned to '.$userDetails->employee_name.' '.'(reviewer)';
                        $activity->role = $creator;
                        $activity->created_by = $request->created_by;
                        $activity->created_date = now();
                        $activity->save();

                        if ($userDetails) {
                            try {
                                Mail::to($userDetails->email_address)->queue(new AssignmentNotificationMail([
                                    'name' => $userDetails->employee_name,
                                    'role' => 'reviewer',
                                    'project_id' => $customId,
                                    'title' => $request->title,
                                    'duration' => $request->projectduration,
                                    'createdBy' => $created->employee_name,
                                    'created_by_role' => $creator,
                                    // 'unit'       => $details->$durationUnit ?? null, // prevent undefined property error
                                ]));
                                // sleep(5);

                                Log::info("Email sent to reviewer ({$userDetails->email_address}).");
                            } catch (\Exception $e) {
                                Log::error('Failed to send email to reviewer: '.$e->getMessage());
                            }
                        } else {
                            Log::warning("User not found for reviewer ID: {$user['reviewer']}");
                        }

                        ProjectLogs::create([
                            'project_id' => $details->id,
                            'employee_id' => $user['reviewer'],
                            'assigned_date' => $user['reviewerAssignedDate'],
                            'status' => $user['reviewerStatus'],
                            'status_date' => $user['reviewerStatusDate'],
                            'status_type' => 'reviewer',
                            'created_by' => $request->created_by,
                            'created_date' => date('Y-m-d H:i:s'),
                        ]);

                        // Update or create project status
                        ProjectStatus::create([
                            'project_id' => $details->id,
                            'assign_id' => $user['reviewer'],
                        ]);
                    }
                }

                if (is_array($request->statistican) && ! empty($request->statistican)) {
                    foreach ($request->statistican as $user) {

                        $assigned_d = new ProjectAssignDetails;
                        $assigned_d->project_id = $details->id;
                        $assigned_d->assign_user = $user['statistican'];
                        $assigned_d->assign_date = $user['statisticanAssignedDate'];
                        $assigned_d->status = $user['statisticanStatus'];
                        $assigned_d->status_date = $user['statisticanStatusDate'];
                        $assigned_d->project_duration = $user['statisticanProjectDuration'];
                        $assigned_d->comments = $user['statistican_comment'] ?? '';
                        $assigned_d->type = 'statistican';
                        $assigned_d->created_by = $request->created_by;
                        $assigned_d->save();

                        $userDetails = User::find($user['statistican']);
                        $created = User::with(['createdByUser'])->find($request->created_by);
                        $creator = $created?->createdByUser?->name ?? 'Admin';
                        $activity = new ProjectActivity;
                        $activity->project_id = $details->id;
                        $activity->activity = 'Project assigned to '.$userDetails->employee_name.' '.'(statistican)';
                        $activity->role = $creator;
                        $activity->created_by = $request->created_by;
                        $activity->created_date = now();
                        $activity->save();

                        if ($userDetails) {
                            try {
                                Mail::to($userDetails->email_address)->queue(new AssignmentNotificationMail([
                                    'name' => $userDetails->employee_name,
                                    'role' => 'statistican',
                                    'project_id' => $customId,
                                    'title' => $details->title,
                                    'duration' => $details->projectduration,
                                    'createdBy' => $created->employee_name,
                                    'created_by_role' => $creator,
                                    // 'unit' => $details->$durationUnit ?? null, // prevent undefined property error
                                ]));
                                // sleep(5);

                                Log::info("Email sent to statistican ({$userDetails->email_address}).");
                            } catch (\Exception $e) {
                                Log::error('Failed to send email to statistican: '.$e->getMessage());
                            }
                        } else {
                            Log::warning("User not found for statistican ID: {$user['statistican']}");
                        }

                        ProjectLogs::create([
                            'project_id' => $details->id,
                            'employee_id' => $user['statistican'],
                            'assigned_date' => $user['statisticanAssignedDate'],
                            'status' => $user['statisticanStatus'],
                            'status_date' => $user['statisticanStatusDate'],
                            'status_type' => 'statistican',
                            'created_by' => $request->created_by,
                            'created_date' => date('Y-m-d H:i:s'),
                        ]);

                        // Update or create project status
                        ProjectStatus::create([
                            'project_id' => $details->id,
                            'assign_id' => $user['statistican'],
                        ]);
                    }
                }

                if (is_array($request->journal) && ! empty($request->journal)) {
                    foreach ($request->journal as $user) {

                        $assigned_d = new ProjectAssignDetails;
                        $assigned_d->project_id = $details->id;
                        $assigned_d->assign_user = $user['journal'];
                        $assigned_d->assign_date = $user['journalAssignedDate'];
                        $assigned_d->status = $user['journalStatus'];
                        $assigned_d->status_date = $user['journalStatusDate'];
                        $assigned_d->project_duration = $user['journalProjectDuration'];
                        $assigned_d->comments = $user['journal_comment'] ?? '';
                        $assigned_d->type = 'publication_manager';
                        $assigned_d->type_of_article = $user['type_of_article'];
                        $assigned_d->review = $user['review'];
                        $assigned_d->created_by = $request->created_by;
                        $assigned_d->save();

                        ProjectLogs::create([
                            'project_id' => $details->id,
                            'employee_id' => $user['journal'],
                            'assigned_date' => $user['journalAssignedDate'],
                            'status' => $user['journalStatus'],
                            'status_date' => $user['journalStatusDate'],
                            'status_type' => 'publication_manager',
                            'created_by' => $request->created_by,
                            'created_date' => date('Y-m-d H:i:s'),
                        ]);

                        // Update or create project status
                        ProjectStatus::create([
                            'project_id' => $details->id,
                            'assign_id' => $user['journal'],
                        ]);
                    }
                }

                $roles = [
                    'writer' => 'Writer',
                    'reviewer' => 'Reviewer',
                    'statistican' => 'Statistician',
                    'journal' => 'Publication Manager',
                ];

                foreach ($roles as $key => $role) {
                    if (! empty($request->$key)) {
                        $userDetails = User::where('id', $request->$key)->first();

                        if ($userDetails) {
                            if (! empty($userDetails->email_address)) {
                                $durationKey = $key.'_project_duration';
                                $durationUnit = $key.'_duration_unit';
                                try {
                                    Mail::to($userDetails->email_address)->send(new AssignmentNotificationMail([
                                        'name' => $userDetails->employee_name,
                                        'role' => $role,
                                        'project_id' => $customId,
                                        'title' => $details->title,
                                        // 'duration' => $details->$durationKey,
                                        'duration' => $details->projectduration,
                                        'unit' => $details->$durationUnit,

                                    ]));
                                    Log::info("Email sent to {$role} ({$userDetails->email_address}).");
                                } catch (\Exception $e) {
                                    Log::error("Failed to send email to {$role}: ".$e->getMessage());
                                }
                            } else {
                                Log::error("{$role} email is empty or invalid.");
                            }
                        } else {
                            Log::error("{$role} not found.");
                        }
                    } else {
                        Log::error("No {$role} ID provided.");
                    }
                }

                if ($request->has('entryprocess_documents') && is_array($request->entryprocess_documents)) {
                    $entryprocessDocuments = []; // To store formatted document data for response
                    $defaultSpecificOption = null; // Store the first valid specificOption

                    foreach ($request->entryprocess_documents as $document) {
                        // Validate that specificOption and file keys exist and are arrays
                        if (isset($document['file']) && is_array($document['file'])) {
                            if (! empty($document['specificOption'])) {
                                if (is_array($document['specificOption'])) {
                                    Log::info('test1');
                                    $defaultSpecificOption = $document['specificOption'];
                                } elseif (is_string($document['specificOption'])) {
                                    Log::info('test2');
                                    $defaultSpecificOption = [$document['specificOption']];
                                    $document['specificOption'] = $defaultSpecificOption;
                                }
                            } elseif (isset($defaultSpecificOption)) {
                                Log::info('test3');
                                $document['specificOption'] = $defaultSpecificOption;
                            }
                            $fileNames = [];

                            $entryDocument = new EntryDocument;
                            $entryDocument->entry_process_model_id = $details->id;
                            $entryDocument->select_document = json_encode($document['specificOption'], JSON_UNESCAPED_UNICODE);
                            $entryDocument->created_by = $request->created_by ?? '-';
                            $entryDocument->save();

                            foreach ($document['file'] as $file) {
                                if (! empty($file)) {
                                    $originalName = $file->getClientOriginalName();
                                    $originalExtension = $file->getClientOriginalExtension();

                                    $cleanedName = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($originalName, PATHINFO_FILENAME));
                                    $uniqueName = $cleanedName.'.'.$originalExtension;

                                    $path = public_path('uploads');

                                    if (! is_dir($path)) {
                                        mkdir($path, 0775, true);
                                    }

                                    $file->move($path, $uniqueName);

                                    $documentList = new EntryDocumentsList;
                                    $documentList->document_id = $entryDocument->id;
                                    $documentList->file = $uniqueName;
                                    $documentList->original_name = $cleanedName.'.'.$originalExtension;
                                    $documentList->save();

                                    $fileNames[] = $uniqueName;
                                }
                            }

                            // Format data for the response
                            $entryprocessDocuments[] = [
                                'specificOption' => $document['specificOption'],
                                'file' => $fileNames,
                            ];
                        }
                    }

                    // Return a successful response
                    return response()->json([
                        'entryprocess_documents' => $entryprocessDocuments,
                    ], 200);
                }
            });
            // Return response with project_id

            return response()->json([
                'success' => true,
                'message' => 'Entry created successfully',
                'project_id' => $details->id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    // public function show(string $id, Request $request)
    // {
    //     $created_by = $request->createdBy;
    //     $details = EntryProcessModel::with([
    //         'institute',
    //         'department',
    //         'profession',
    //         'documents',
    //         // 'documents' => function ($query) use ($created_by) {
    //         //     $query->where('created_by', $created_by);
    //         // },
    //         'paymentProcess',
    //         'paymentProcess.paymentData',
    //         'projectcomment',
    //         // 'projectcomment' => function ($query) use ($created_by) {
    //         //     $query->where('assignee', $created_by);
    //         // },
    //         'writerData',
    //         'reviewerData',
    //         'statisticanData',
    //         'projectMailStatus',
    //         'journalData',
    //         'employeePaymentDetail',
    //         'journalPaymentDetails',
    //     ])
    //         ->find($id);

    //     if ($details && $details->documents) {
    //         $documents = $details->documents
    //             ->flatMap(function ($doc) {
    //                 return json_decode($doc->select_document, true);
    //             })
    //             ->unique()
    //             ->values();

    //         $details->documents_title = $documents;
    //     }

    //     $rejected_lists = ProjectLogs::with(['userData', 'rejectReasons:id,project_id,content,status,created_by,created_at'])
    //         ->where('status', 'rejected')
    //         ->where('project_id', $id)
    //         ->orderBy('created_at', 'desc')
    //         ->get();
    //     $rejected_list = $rejected_lists->map(function ($item) {
    //         $currentAssignId = optional($item->userData)->id;

    //         $filteredRejects = $item->rejectReasons
    //             ->where('created_by', $currentAssignId)
    //             ->where('status', 'rejected')
    //             ->pluck('content')
    //             ->implode(', ');

    //         return [
    //             'project_id' => $item->project_id,
    //             'content' => $filteredRejects,
    //             'status' => $item->status,
    //             'type' => $item->status_type,
    //             'role' => optional(optional($item->userData)->createdByUser)->name ?? 'writer,reviewer',
    //             'employee_name' => optional($item->userData)->employee_name,
    //             'created_at' => $item->created_at,
    //             'assign_id' => $currentAssignId,
    //         ];
    //     });

    //     $details->rejected_list = $rejected_list;

    //     // return response()->json($details);
    //     return response()->json($details->toArray());

    // }

    public function show(string $id, Request $request)
    {
        $created_by = $request->createdBy;

        $details = EntryProcessModel::with([
            'institute',
            'department',
            'profession',
            'documents',
            'paymentProcess',
            'paymentProcess.paymentData',
            'projectcomment',
            'writerData',
            'reviewerData',
            'statisticanData',
            'projectMailStatus',
            'journalData',
            'employeePaymentDetail',
            'journalPaymentDetails',
        ])->find($id);

        // Process documents
        if ($details && $details->documents) {
            $documents = $details->documents
                ->flatMap(fn ($doc) => json_decode($doc->select_document, true))
                ->unique()
                ->values();

            $details->documents_title = $documents;
        }

        // Fix reference_number_file for paymentProcess
        if ($details->paymentProcess) {
            $details->paymentProcess->reference_number_file = $this->decodeJsonArray($details->paymentProcess->reference_number_file);

            // Fix reference_number_file for each paymentData item
            if ($details->paymentProcess->paymentData) {
                $details->paymentProcess->paymentData->transform(function ($item) {
                    $item->reference_number_file = $this->decodeJsonArray($item->reference_number_file);

                    return $item;
                });
            }
        }

        // Process rejected lists
        $rejected_lists = ProjectLogs::with(['userData', 'rejectReasons:id,project_id,content,status,created_by,created_at'])
            ->where('status', 'rejected')
            ->where('project_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $rejected_list = $rejected_lists->map(function ($item) {
            $currentAssignId = optional($item->userData)->id;

            $filteredRejects = $item->rejectReasons
                ->where('created_by', $currentAssignId)
                ->where('status', 'rejected')
                ->pluck('content')
                ->implode(', ');

            return [
                'project_id' => $item->project_id,
                'content' => $filteredRejects,
                'status' => $item->status,
                'type' => $item->status_type,
                'role' => optional(optional($item->userData)->createdByUser)->name ?? 'writer,reviewer',
                'employee_name' => optional($item->userData)->employee_name,
                'created_at' => $item->created_at,
                'assign_id' => $currentAssignId,
            ];
        });

        $details->rejected_list = $rejected_list;

        return response()->json($details);
    }

    /**
     * Safely decode double-encoded JSON strings into arrays
     */
    private function decodeJsonArray($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_string($decoded) ? json_decode($decoded, true) : $decoded;
        }

        return $value;
    }

    public function showProjectView(string $id)
    {
        $details = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'client_name', 'department', 'profession', 'budget', 'process_status', 'hierarchy_level', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration', 'journal_project_duration', 'statistican_project_duration', 'reviewer_project_duration', 'writer_project_duration')->with([
            'institute',
            'department',
            'profession',
            'documents',
            'paymentProcess',
            'paymentProcess.paymentData',
            'writerData',
            'reviewerData',
            'statisticanData',
        ])
            ->where('is_deleted', 0)
            ->find($id);

        return response()->json($details);
    }

    public function projectViewEntry(Request $request, string $id)
    {
        $position = $request->position;
        // $ids = $request->ids;
        // $assign_user = $request->assign_user;
        // $assign_user_array = explode(',', $assign_user);

        $createdBy = $request->query('created_by');
        $assign_user_array = explode(',', $createdBy);
        $peopleIds_pm = People::where('position', '28')
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();
        $details = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'hierarchy_level', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration', 'comment_box', 'created_at', 'updated_at', 'journal_project_duration', 'statistican_project_duration', 'reviewer_project_duration', 'writer_project_duration')->with([
            'institute',
            'department',
            'profession',
            'documents',
            'documentsA',
            'paymentProcess',
            'paymentProcess.paymentData',
            'rejectReason' => function ($query) use ($createdBy) {
                if ($createdBy) {
                    $query->where('created_by', $createdBy);
                }
            },
            'activityData.repliesd',
            'writerData',
            'reviewerData',
            'statisticanData',
            //latest data journalData
            'journalData',

            'projectStatus',
            'projectcomment',
            // 'tcData',

            'tcData' => function ($query) use ($peopleIds_pm) {
                $query->with(['UserDate'])
                    ->where('type', 'team_coordinator')
                    ->where('type_sme', '!=', '-')
                    ->whereIn('created_by', $peopleIds_pm);
                // ->select('project_id', 'assign_user', 'assign_date', 'type_sme', 'status', 'status_date', 'comments', 'type', 'created_by', 'id', 'project_duration', 'type_of_article', 'review', 'is_deleted', 'tcDataStatus');
            },

            'projectcommentR' => function ($query) use ($createdBy) {
                if ($createdBy) {
                    $query->where('assign_user', $createdBy)->latest();
                }
            },
            'projectAcceptStatust' => function ($query) use ($createdBy) {
                if ($createdBy) {
                    $query->where('assign_id', $createdBy);
                }
            },
        ])
            ->where('is_deleted', 0)
            ->where('project_id', $id)
            ->orderBy('id', 'desc')
            ->first();

        if ($details) {
            $allFiles = EntryDocumentsList::whereIn('document_id', $details->documents->pluck('id'))->get();
            $allFilesA = ActivityDocuments::whereIn('activity_id', $details->documentsA->pluck('id'))->get();

            // Base query

            $commentsQuery = Activity::with(['createdByUser.createdUserP', 'file'])
                ->where('project_id', $details->id)
                ->orderBy('created_at', 'desc');

            // Fetch all comments
            $comments = $commentsQuery->get();

            // Format the result
            $commentslist = $comments->map(function ($item) {
                return [
                    'employee_name' => $item->createdByUser->employee_name ?? null,
                    'position' => $item->createdByUser->position ?? null,
                    'name' => $item->createdByUser->createdUserP->name ?? null,
                    'activity' => $item->activity,
                    'createdby_name' => $item->createdby_name,
                    'created_date' => $item->created_date,
                    'file' => $item->file,
                ];
            });

            $admin_document = EntryDocument::with(['createdByUsers.createdUserP', 'file'])
                ->where('entry_process_model_id', $details->id)
                ->get();

            $details->commentslist = $commentslist;
            $details->common_doc = $admin_document;

            // $documentsFiltered = $details->documents->map(function ($doc) use ($allFiles,  $createdBy) {

            //     $files_g = $allFiles->where('document_id', $doc->id);
            //     $employee = User::where('id', $doc->created_by)
            //         ->select('id', 'employee_name', 'profile_image', 'position')
            //         ->orderBy('created_at', 'desc')
            //         ->first();
            //     $userPosition = $employee->position ?? 'N/A';
            //     $roleId = [];
            //     $roleName = [];

            //     if ($userPosition !== 'Admin') {
            //         $positions = explode(',', $userPosition);
            //         $roles = Roles::whereIn('id', $positions)->get();

            //         foreach ($roles as $role) {
            //             $roleId[] = $role->id;
            //             $roleName[] = $role->name;
            //         }
            //     } else {
            //         $roleId = ['Admin'];
            //         $roleName = ['Admin'];
            //     }

            //     return [
            //         'doc_id' => $doc->id,
            //         'created_by' => $doc->created_by,
            //         'created_at' => $doc->created_at->format('Y-m-d H:i:s'),
            //         'type' => 'document',
            //         'employee_name' => $employee->employee_name ?? 'N/A',
            //         'position' => $userPosition,
            //         // 'role_name' => $roleName,
            //         'files' => $files_g->mapWithKeys(function ($file) {
            //             return [

            //                 'file_path' => $file->file,
            //                 'file_create' => $file->created_at->format('Y-m-d H:i:s'),
            //                 'original_name' => $file->original_name ?? 'Unknown'
            //             ];
            //         })
            //     ];
            // });
            // $documentsAFiltered = $details->documentsA->map(function ($doc) use ($allFilesA, $createdBy, $details) {

            //     $files_g = $allFilesA->where('activity_id', $doc->id);
            //     $employee = User::where('id', $createdBy)
            //         ->select('id', 'employee_name', 'profile_image', 'position')
            //         ->orderBy('created_at', 'desc')
            //         ->first();

            //     $userPosition = $employee->position ?? 'N/A';
            //     $roleId = [];
            //     $roleName = [];

            //     $project_id = $details->id;

            //     if ($userPosition !== 'Admin') {
            //         $positions = explode(',', $userPosition);
            //         $roles = Roles::whereIn('id', $positions)->get();

            //         foreach ($roles as $role) {
            //             $roleId[] = $role->id;
            //             $roleName[] = $role->name;
            //         }
            //     } else {
            //         $roleId = ['Admin'];
            //         $roleName = ['Admin'];
            //     }

            //     $comments = Activity::where('id', $doc->id)
            //         ->where('project_id', $project_id)
            //         ->get()
            //         ->map(function ($item) {
            //             return [
            //                 'comment' => $item->activity,
            //             ];
            //         });

            //     return [
            //         'doc_id' => $doc->id,
            //         'created_by' => $doc->created_by,
            //         'created_at' => $doc->created_at->format('Y-m-d H:i:s'),
            //         'type' => 'document',
            //         'employee_name' => $employee->employee_name ?? 'N/A',
            //         'position' => $userPosition,
            //         'roles' => [
            //             'role_names' => $roleName,
            //         ],
            //         'comments' => $comments,
            //         'files' => $files_g->map(function ($file) {
            //             return [
            //                 'file_path' => $file->files,
            //                 'file_create' => $file->created_at->format('Y-m-d H:i:s'),
            //                 'original_name' => $file->original_name ?? 'Unknown'
            //             ];
            //         })->values()->toArray()
            //     ];
            // });

        } else {
            $details->documents_list = collect();
        }

        $completedLogs = ProjectLogs::where('project_id', $details->id)
            ->whereIn('employee_id', [$details->writer, $details->reviewer, $details->statistican, $details->journal])
            ->where('status', 'completed')
            ->select('id', 'status', 'employee_id', 'project_id', 'created_date')
            ->latest()
            ->get();

        $writerCompleted = $completedLogs->where('employee_id', $details->writer)->first();
        $reviewerCompleted = $completedLogs->where('employee_id', $details->reviewer)->first();
        $statisticanCompleted = $completedLogs->where('employee_id', $details->statistican)->first();
        $journalCompleted = $completedLogs->where('employee_id', $details->journal)->first();
        $journalStatus = ProjectAssignDetails::where('project_id', $details->id)
            ->where('type', 'publication_manager')
            // ->select('status')
            ->latest()
            ->first();
        $peopleIds_pm = People::where('position', '28')
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();
        $tcDataStatus = ProjectAssignDetails::where('project_id', $details->id)
            ->where('type', 'team_coordinator')
            ->whereIn('created_by', $peopleIds_pm)
            // ->latest()
            ->first();
        // Get all select_document values for this project, flatten and remove duplicates
        $documents = EntryDocument::where('entry_process_model_id', $details->id)
            ->pluck('select_document')
            ->flatMap(function ($doc) {
                $decoded = json_decode($doc, true);

                return is_array($decoded) ? $decoded : [];
            })
            ->unique()
            ->values();
        $writerFind = ProjectAssignDetails::where('project_id', $details->id)->where('type', 'writer')
            ->where('assign_user', $assign_user_array)
            ->select('type', 'status')->get();

        $reviewerFind = ProjectAssignDetails::where('project_id', $details->id)->where('type', 'reviewer')
            ->where('assign_user', $assign_user_array)
            ->select('type', 'status')->get();
        $statisticanFind = ProjectAssignDetails::where('project_id', $details->id)->where('type', 'statistican')
            ->where('assign_user', $assign_user_array)
            ->select('type', 'status')->get();
        $completedStatus = ProjectAssignDetails::where('project_id', $details->id)
            ->where('assign_user', $createdBy)
            ->select('status')->first();

        // Format as array of objects with 'value' key
        $formatted = $documents->map(function ($item) {
            return ['value' => $item];
        })->all();

        // For backward compatibility, keep $document as the array of values
        $document = $documents->all();

        if ($details) {
            $userhrms = DB::connection('mysql_medics_hrms')
                ->table('employee_details')
                ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                ->where('id', $details->created_by)
                ->where('status', '1')
                ->first();

            $assignedEmployee = DB::connection('mysql_medics_hrms')
                ->table('employee_details')
                ->where('id', $details->assign_by)
                ->select('id', 'employee_name', 'profile_image', 'position')
                ->where('status', '1')
                ->first();

            if ($assignedEmployee) {
                $rolename = DB::connection('mysql_medics_hrms')
                    ->table('roles')
                    ->where('id', $assignedEmployee->position)
                    ->value('name');

                $assignedEmployee->role_name = $rolename;
                $assignedEmployee->role_id = $assignedEmployee->position;
            }

            //     foreach ($details as $item) {
            //     $item->tracking_status = $this->getTrackingStatusByProjectId($item->created_by);
            // }

            if ($userhrms) {
                $response = [
                    'project_details' => $details,
                    'journalStatus' => $journalStatus,
                    'tcDataStatus' => $tcDataStatus,
                    'writerFind' => $writerFind,
                    'reviewerFind' => $reviewerFind,
                    'statisticanFind' => $statisticanFind,
                    'employee_details' => [
                        'id' => $userhrms->id,
                        'employee_name' => $userhrms->employee_name,
                        'profile_image' => $userhrms->profile_image,
                    ],
                    'assigned_employee' => $assignedEmployee,
                    'completedStatus' => $completedStatus->status ?? null,
                    'trackingStatus' => $this->getTrackingStatusByProjectId($details->id),
                ];
            } else {
                $response = [
                    'project_details' => $details,
                    'employee_details' => null,
                    'message' => 'Employee not found',
                ];
            }
        } else {
            $response = [
                'project_details' => null,
                'message' => 'Project not found',
            ];
        }

        $employees = User::with(['createdByUser'])
            ->whereIn('position', [7, 8, 10, 11])
            ->select('id', 'employee_name', 'profile_image', 'position')
            ->get();

        $employees->each(function ($employee) {
            $rolename = DB::connection('mysql_medics_hrms')
                ->table('roles')
                ->where('id', $employee->position)
                ->first();

            $employee->role_name = $rolename;
        });
        $rejected_lists = ProjectLogs::with(['userData', 'rejectReasons:id,project_id,content,status,created_by,created_at'])
            ->where('status', 'rejected')
            ->where('project_id', $details->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $revert_list = ProjectLogs::with([
            'userData',
            'rejectReasons:id,project_id,content,status,created_by,created_at',
        ])
            ->where('status', 'revert')
            ->where('project_id', $details->id)
            ->get()
            ->sortByDesc(function ($item) {
                return optional($item->rejectReasons->first())->created_at ?? $item->created_at;
            })
            ->values();

        $rejected_list = $rejected_lists->map(function ($item) {
            $currentAssignId = optional($item->userData)->id ?? null; // the assign_id of current item

            // Filter rejectReasons where assign_id matches
            $filteredRejects = $item->rejectReasons
                ->where('created_by', $currentAssignId)
                ->where('status', 'rejected')
                ->pluck('content')
                ->implode(', ');

            return (object) [
                'project_id' => $item->project_id ?? null,
                'content' => $filteredRejects,
                'status' => $item->status,
                'role' => optional($item->userData->createdByUser ?? '')->name ?? 'writer,reviewer',
                'employee_name' => optional($item->userData)->employee_name ?? null,
                'created_at' => $item->created_at ?? null,
                'assign_id' => $currentAssignId,
            ];
        });

        $revert_list = $revert_list->map(function ($item) {
            $currentAssignId = optional($item->userData)->id ?? null; // the assign_id of current item

            // Filter rejectReasons where assign_id matches
            $filteredRejects = $item->rejectReasons
                ->where('created_by', $currentAssignId)
                ->where('status', 'revert')
                ->pluck('content')
                ->implode(', ');

            return (object) [
                'project_id' => $item->project_id ?? null,
                'content' => $filteredRejects,
                'status' => $item->status,
                'role' => optional($item->userData->createdByUser ?? '')->name ?? 'writer,reviewer',
                'employee_name' => optional($item->userData)->employee_name ?? null,
                'created_at' => $item->created_at ?? null,
                'assign_id' => $currentAssignId,
            ];
        });

        $peopleIds_sme = People::where('position', '28')
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();

        //getting last status in projectLogs
        $projectLog = ProjectLogs::where('project_id', $details->id)
            ->where('status_type', 'publication_manager')
            ->orderBy('created_at', 'desc')
            ->first();

        $status = $projectLog ? $projectLog->status : '';

        return response()->json([
            'response' => $response,
            'employees' => $employees,
            'writerCompleted' => $writerCompleted,
            'reviewerCompleted' => $reviewerCompleted,
            'statisticanCompleted' => $statisticanCompleted,
            'journalCompleted' => $journalCompleted,
            'rejected_list' => $rejected_list,
            'revert_list' => $revert_list,
            'duration_option' => $formatted,
            'duration_options' => $document,
            'publication_status' => $status,
        ]);
    }

    public function getProjectDocumentDownload(Request $request)
    {
        $createdby = $request->query('createdby');
        $date = $request->query('date');
        $type = $request->query('type');
        $pid = $request->query('pid');
        $filePaths = [];

        if ($type === 'document') {
            // Get the document IDs
            $documentIds = EntryDocument::where('created_by', $createdby)
                ->where('entry_process_model_id', $pid)
                ->whereDate('created_at', $date)
                ->pluck('id')
                ->toArray();

            // Get the associated files
            $downloadfiles = EntryDocumentsList::whereIn('document_id', $documentIds)
                ->select('file')
                ->get();

            foreach ($downloadfiles as $file) {
                $filePath = public_path('uploads/'.$file->file);
                if (File::exists($filePath)) {
                    $filePaths[] = url('uploads/'.$file->file);
                }
            }
        } else {
            // Get the activity IDs
            $documentIds = Activity::where('created_by', $createdby)
                ->where('project_id', $pid)
                ->whereDate('created_at', $date)
                ->pluck('id')
                ->toArray();

            $downloadfiles = ActivityDocuments::whereIn('activity_id', $documentIds)
                ->select('files')
                ->get();

            foreach ($downloadfiles as $file) {
                $filePath = public_path('activity_files/'.$file->files);
                if (File::exists($filePath)) {
                    $filePaths[] = url('activity_files/'.$file->files);
                }
            }
        }

        if (! empty($filePaths)) {
            return response()->json(['files' => $filePaths], 200);
        }

        return response()->json(['error' => 'No files found for the given criteria'], 404);
    }

    public function getEmails()
    {
        $positions = [13, 14];

        try {
            $employees = User::with(['createdByUser'])->where('position', '!=', 'Admin')
                ->where('position', '!=', '13')
                ->where('position', '!=', '14')
                ->select('id', 'employee_name', 'profile_image', 'position')
                ->get();

            $result = [];
            foreach ($employees as $employee) {
                $position = strtolower($employee->position);

                // Ensure the position key exists in the result array
                if (! isset($result[$position])) {
                    $result[$position] = [];
                }

                // Add the employee details to the correct position key in the result array
                $result[$position][] = [
                    'id' => $employee->employee_id,
                    'email' => $employee->email_address,
                ];
            }

            return response()->json($result);
        } catch (\Exception $e) {
            // Log the exception and return an error response
            Log::error($e->getMessage());

            return response()->json(['error' => 'Unable to fetch data'], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    // public function update(Request $request, $id)
    // {

    //     $selectedOption = $request->type_of_work;
    //     $customId = $request->project_id;

    //     DB::transaction(function () use ($selectedOption, $request, &$customId, $id) {
    //         $details = EntryProcessModel::find($id);
    //         if (! $details) {
    //             throw new \Exception('Entry not found');
    //         }
    //         $roles = [];

    //         if (! empty($request->writer) && $details->writer !== $request->writer) {
    //             $roles['writer'] = 'Writer';
    //         }
    //         if (! empty($request->reviewer) && $details->reviewer !== $request->reviewer) {
    //             $roles['reviewer'] = 'Reviewer';
    //         }
    //         if (! empty($request->statistican) && $details->statistican !== $request->statistican) {
    //             $roles['statistican'] = 'Statistician';
    //         }

    //         $roles = [
    //             'writer' => 'Writer',
    //             'reviewer' => 'Reviewer',
    //             'statistican' => 'Statistician',
    //             'journal' => 'Publication Manager',
    //         ];

    //         foreach ($roles as $key => $role) {
    //             if (! empty($request->$key)) {
    //                 $userDetails = User::where('id', $request->$key)->first();

    //                 if ($userDetails) {
    //                     if (! empty($userDetails->email_address)) {
    //                         $durationKey = $key.'_project_duration';
    //                         $durationUnit = $key.'_duration_unit';
    //                         try {
    //                             Mail::to($userDetails->email_address)->send(new AssignmentNotificationMail([
    //                                 'name' => $userDetails->employee_name,
    //                                 'role' => $role,
    //                                 'project_id' => $customId,
    //                                 'title' => $details->title,
    //                                 'duration' => $details->$durationKey,
    //                                 'unit' => $details->$durationUnit,
    //                             ]));
    //                             Log::info("Email sent to {$role} ({$userDetails->email_address}).");
    //                         } catch (\Exception $e) {
    //                             Log::error("Failed to send email to {$role}: ".$e->getMessage());
    //                         }
    //                     } else {
    //                         Log::error("{$role} email is empty or invalid.");
    //                     }
    //                 } else {
    //                     Log::error("{$role} not found.");
    //                 }
    //             } else {
    //                 Log::error("No {$role} ID provided.");
    //             }
    //         }

    //         if ($details->project_id !== $request->project_id) {
    //             $duplicateProjectId = EntryProcessModel::where('project_id', $request->project_id)
    //                 ->where('id', '!=', $id)
    //                 ->where('is_deleted', 0)
    //                 ->exists();

    //             if ($duplicateProjectId) {
    //                 throw new \Exception("Project ID '{$request->project_id}' is already in use.");
    //             }
    //         }

    //         if ($details->type_of_work !== $selectedOption) {
    //             $lastEntry = EntryProcessModel::where('type_of_work', $selectedOption)
    //                 ->orderBy('id', 'desc')
    //                 ->lockForUpdate()
    //                 ->first();

    //             $increment = $lastEntry ? (int) substr($lastEntry->project_id, strlen($selectedOption) + 1) + 1 : 1;
    //             $formattedIncrement = str_pad($increment, 3, '0', STR_PAD_LEFT);
    //             $customId = $selectedOption.'-'.$formattedIncrement;
    //         } else {
    //             $customId = $details->project_id;
    //         }

    //         $details->entry_date = $request->entry_date;
    //         $details->title = $request->title;
    //         $details->project_id = $customId;
    //         $details->type_of_work = $request->type_of_work;
    //         $details->others = $request->others;
    //         $details->client_name = $request->client_name;
    //         $details->email = $request->email;
    //         $details->contact_number = $request->contact_number;
    //         $details->institute = $request->institute;
    //         $details->department = $request->department;
    //         $details->profession = $request->profession;
    //         $details->budget = $request->budget;
    //         $details->process_status = $request->process_status;
    //         $details->process_date = $request->process_date;
    //         $details->hierarchy_level = $request->hierarchy_level;
    //         $details->comment_box = $request->comment_box;
    //         $details->else_project_manager = $request->else_project_manager;
    //         $details->status = $request->status ?? $details->status;
    //         $details->is_deleted = $request->is_deleted ?? $details->is_deleted;
    //         // $details->created_by = $request->created_by;
    //         $details->projectduration = $request->project_duration;
    //         $details->save();

    //         // $userDetails = User::find($writer);
    //         // if ($request->created_by != '86') {
    //         //     $activity = new ProjectActivity;
    //         //     $activity->project_id = $details->id;
    //         //     $activity->activity = 'Project has edited ';
    //         //     $activity->created_by = $request->created_by;
    //         //     $activity->created_date = now();
    //         //     $activity->save();
    //         // }
    //         $created = User::with('createdByUser')->find($request->created_by);
    //         $creator = $created?->createdByUser?->name ?? 'Admin';
    //         if ($request->created_by != '86') {

    //             ProjectActivity::firstOrCreate(
    //                 [
    //                     'project_id' => $details->id,
    //                     'activity' => 'Project has edited ',
    //                     'created_by' => $request->created_by,
    //                     'role' => $creator,
    //                 ],
    //                 [
    //                     'created_date' => now(),
    //                 ]
    //             );
    //         }

    //         if ($request->process_status) {

    //             $exists = ProjectActivity::where('project_id', $details->id)
    //                 ->where('activity', 'project process status  marked as '.$request->process_status)
    //                 ->exists();

    //             // if (! $exists) {
    //             $activity = new ProjectActivity;
    //             $activity->project_id = $details->id;
    //             $activity->activity = 'project process status  marked as '.$request->process_status;
    //             $activity->role = $creator;
    //             $activity->created_by = $request->created_by;
    //             $activity->created_date = now();
    //             $activity->save();
    //             // }
    //         }

    //         if ($request->process_status === 'withdrawal') {

    //             $positions = [13, 14, 'Admin'];

    //             $users = User::whereIn('position', $positions)
    //                 ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
    //                 ->get()
    //                 ->keyBy('position');

    //             $emails = [
    //                 'projectManager' => $users->get(13)?->email_address,
    //                 'teamManager' => $users->get(14)?->email_address,

    //                 'adminEmail' => $users->get('Admin')?->email_address,

    //             ];

    //             $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $request->created_by)->first();
    //             $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

    //             if (! empty($emails['projectManager']) && ! empty($emails['teamManager']) && ! empty($emails['adminEmail']) && ! empty($employeedetails->email_address)) {
    //                 try {
    //                     // Send email to writer with CC to others
    //                     Mail::to($emails['projectManager'], $emails['teamManager'])
    //                         ->cc($emails['adminEmail'])
    //                         ->send(new TaskCompleteEmail([
    //                             'projectManagerEmail' => $emails['projectManager'],
    //                             'teamManagerEmail' => $emails['teamManager'],
    //                             'adminEmail' => $emails['adminEmail'],

    //                             'employee_name' => $employeedetails->employee_name,
    //                             'role' => $employeedetails->createdByUser?->name,

    //                             'project_id' => $projectDetails->project_id,
    //                             'status' => $request->process_status,
    //                             'detail_name' => $request->process_status,
    //                         ]));

    //                     Log::info('Email sent to project manager and team manager');
    //                 } catch (\Exception $e) {
    //                     Log::error('Mail failed: '.$e->getMessage());

    //                 }

    //             }
    //         }

    //         //payment update
    //         if (! is_null($request->payment_status) && trim($request->payment_status) !== '') {
    //             Log::info('Entering payment_status condition', ['payment_status' => $request->payment_status]);
    //             $details_p = PaymentStatusModel::where('id', $request->payment_id)->first();

    //             if ($details_p) {
    //                 Log::info('Updating existing payment status', ['payment_id' => $request->payment_id]);
    //                 // $details_p->project_id = $details->id;
    //                 $details_p->payment_status = $request->payment_status;
    //                 $details_p->created_by = $request->created_by;
    //                 $details_p->save();

    //                 // $userDetails = User::find($writer);
    //                 // if ($request->created_by != '86') {
    //                 //     $activity = new ProjectActivity;
    //                 //     $activity->project_id = $details->id;
    //                 //     $activity->activity = 'Payment marked as '.$request->payment_status;
    //                 //     $activity->created_by = $request->created_by;
    //                 //     $activity->created_date = now();
    //                 //     $activity->save();
    //                 // }

    //                 // if ($request->created_by != '86') {

    //                 //     ProjectActivity::firstOrCreate(
    //                 //         [
    //                 //             'project_id' => $details->id,
    //                 //             'activity' => 'Payment marked as '.$request->payment_status,
    //                 //             'created_by' => $request->created_by,
    //                 //         ],
    //                 //         [
    //                 //             'created_date' => now(),
    //                 //         ]
    //                 //     );
    //                 // }

    //                 $created = User::with('createdByUser')->find($request->created_by);

    //                 $employee = $created?->employee_name ?? 'Mohamed Ali';
    //                 $creator = $created?->createdByUser?->name ?? 'Admin';
    //                 $activityText = "Payment marked as {$request->payment_status} by {$employee} ({$creator})";
    //                 if ($request->created_by != 86) {

    //                     ProjectActivity::firstOrCreate(
    //                         [
    //                             'project_id' => $details->id,
    //                             'activity' => $activityText,
    //                             'role' => $creator,
    //                             'created_by' => $request->created_by,
    //                         ],
    //                         [
    //                             'created_date' => now(),
    //                         ]
    //                     );
    //                 }

    //             } else {
    //                 Log::info('Creating new payment status', ['project_id' => $details->id]);

    //                 $details_p = new PaymentStatusModel;
    //                 $details_p->project_id = $details->id;
    //                 $details_p->payment_status = $request->payment_status;
    //                 $details_p->created_by = $request->created_by;
    //                 $details_p->save();
    //             }
    //             if (! empty($request->payment_details) && is_array($request->payment_details)) {
    //                 foreach ($request->payment_details as $pay) {
    //                     // Check if the payment_detail already exists by its ID
    //                     if (isset($pay['id']) && $pay['id']) {
    //                         // If the ID exists, update the record
    //                         $paymentDetails = PaymentDetails::find($pay['id']);

    //                         // Ensure the payment detail exists before updating
    //                         if ($paymentDetails) {
    //                             $paymentDetails->payment = $pay['payment'] ?? $paymentDetails->payment;
    //                             $paymentDetails->payment_date = $pay['payment_date'] ?? $paymentDetails->payment_date;

    //                             // Only update payment_type if it was previously null
    //                             if ($paymentDetails->payment_type === null) {
    //                                 $paymentDetails->payment_type = $request->payment_status ?? $paymentDetails->payment_type;
    //                             }

    //                             $paymentDetails->save();
    //                         }
    //                     } else {
    //                         // If no ID, create a new payment detail record
    //                         $existingPayment = PaymentDetails::where('payment_id', $details_p->id)
    //                             ->where('payment', $pay['payment'] ?? null)
    //                             ->where('payment_date', $pay['payment_date'] ?? null)
    //                             ->where('payment_type', $request->payment_status ?? null)
    //                             ->first();

    //                         if (! $existingPayment) {
    //                             $paymentDetails = new PaymentDetails;
    //                             $paymentDetails->payment_id = $details_p->id;
    //                             $paymentDetails->payment = $pay['payment'] ?? null;
    //                             $paymentDetails->payment_date = $pay['payment_date'] ?? null;
    //                             $paymentDetails->payment_type = $request->payment_status ?? null;
    //                             $paymentDetails->save();
    //                         }
    //                     }
    //                 }
    //             } else {
    //                 Log::error('payment_details is either null or not an array', ['request_data' => $request->all()]);
    //             }

    //             // Add new payment details if provided
    //             if ($request->has('new_payment_details') && is_array($request->new_payment_details)) {
    //                 foreach ($request->new_payment_details as $newPay) {
    //                     $existingNewPayment = PaymentDetails::where('payment_id', $details_p->id)
    //                         ->where('payment', $newPay['payment'] ?? null)
    //                         ->where('payment_date', $newPay['payment_date'] ?? null)
    //                         ->where('payment_type', $request->payment_status ?? null)
    //                         ->first();

    //                     if (! $existingNewPayment) {
    //                         $newPaymentDetails = new PaymentDetails;
    //                         $newPaymentDetails->payment_id = $details_p->id;
    //                         $newPaymentDetails->payment = $newPay['payment'] ?? null;
    //                         $newPaymentDetails->payment_date = $newPay['payment_date'] ?? null;
    //                         $newPaymentDetails->payment_type = $request->payment_status ?? null;
    //                         $newPaymentDetails->save();
    //                     }
    //                 }
    //             }

    //             $paymentLog = PaymentLogs::where('project_id', $request->project_id)
    //                 ->where('payment_id', $details_p->id)
    //                 ->where('payment_status', $request->payment_status)
    //                 ->first();

    //             if ($paymentLog) {
    //                 $paymentLog->created_date = date('Y-m-d H:i:s'); // Current timestamp
    //                 $paymentLog->save();
    //             } else {
    //                 PaymentLogs::create([
    //                     'project_id' => $details->id,
    //                     'payment_id' => $details_p->id,
    //                     'payment_status' => $request->payment_status,
    //                     'created_by' => $request->created_by,
    //                     'created_date' => date('Y-m-d H:i:s'), // Current timestamp
    //                 ]);
    //             }
    //         }
    //         //payment end

    //         // $activity = new ProjectActivity;
    //         // $activity->project_id = $details->id;
    //         // $activity->activity = 'updated successfully';
    //         // $activity->created_by = $request->created_by;
    //         // $activity->created_date = date('Y-m-d H:i:s');
    //         // $activity->save();

    //         // ProjectActivity::firstOrCreate(
    //         //     [
    //         //         'project_id' => $details->id,
    //         //         'activity' => 'updated successfully',
    //         //         'created_by' => $request->created_by,
    //         //     ],
    //         //     [
    //         //         'created_date' => now(),
    //         //     ]
    //         // );

    //         if (! empty($request->process_status)) {
    //             $existingStatus = ProjectViewStatus::where('project_id', $details->id)
    //                 ->where('project_status', $request->process_status)
    //                 ->where('created_by', $request->created_by)
    //                 ->latest()
    //                 ->first();

    //             if (! $existingStatus) {
    //                 $comments = new ProjectViewStatus;
    //                 $comments->project_id = $details->id;
    //                 $comments->project_status = $request->process_status;
    //                 $comments->created_by = $request->created_by;
    //                 $comments->created_date = date('Y-m-d H:i:s');
    //                 $comments->save();
    //             }
    //         }

    //         if (! empty($request->comment_box)) {

    //             $existingComment = Commends::where('project_id', $details->id)
    //                 ->where('assignee', $request->created_by)
    //                 ->first();

    //             if ($existingComment) {
    //                 $existingComment->commend_box = $request->comment_box ?? $existingComment->commend_box;
    //                 $existingComment->save();
    //             } else {
    //                 $comments = new Commends;
    //                 $comments->project_id = $details->id;
    //                 $comments->commend_box = $request->comment_box ?? null;
    //                 $comments->created_by = $request->created_by;
    //                 $comments->created_date = date('Y-m-d H:i:s');
    //                 $comments->assignee = $request->created_by;
    //                 $comments->save();
    //             }
    //         }

    //         //project status
    //         if (! empty($request->writer) && is_array($request->writer)) {
    //             foreach ($request->writer as $user) {
    //                 Log::info('check data1');
    //                 $writer = $user['writer'] ?? null;
    //                 $writerid = $user['writerid'] ?? null;

    //                 $existingAssignment = ProjectAssignDetails::where('project_id', $details->id)
    //                     ->where('id', $writerid)
    //                     ->where('type', 'writer')
    //                     ->first();

    //                 $existingAssignment3 = ProjectAssignDetails::where('project_id', $details->id)
    //                     ->where('status', 'correction')
    //                     ->where('type', 'team_coordinator')
    //                     // ->where('assign_user', $statistican)
    //                     ->first();

    //                 if ($existingAssignment3) {
    //                     $existingAssignment3->assign_user = $writer;
    //                     $existingAssignment3->assign_date = $user['writerDate'] ?? $existingAssignment3->assign_date;
    //                     $existingAssignment3->status = $user['writerStatus'] ?? $existingAssignment->status;
    //                     $existingAssignment3->status_date = $user['writerStatusDate'] ?? $existingAssignment3->status_date;
    //                     $existingAssignment3->project_duration = $user['writerprojectduration'] ?? $existingAssignment3->project_duration;
    //                     $existingAssignment3->comments = $user['writer_comment'] ?? $existingAssignment3->comments;
    //                     $existingAssignment3->save();
    //                 }

    //                 if ($existingAssignment) {
    //                     // Update existing record'
    //                     $existingAssignment->assign_user = $writer;
    //                     $existingAssignment->assign_date = $user['writerDate'] ?? $existingAssignment->assign_date;
    //                     $existingAssignment->status = $user['writerStatus'] ?? $existingAssignment->status;
    //                     $existingAssignment->status_date = $user['writerStatusDate'] ?? $existingAssignment->status_date;
    //                     $existingAssignment->project_duration = $user['writerprojectduration'] ?? $existingAssignment->project_duration;
    //                     $existingAssignment->comments = $user['writer_comment'] ?? $existingAssignment->comments;
    //                     $existingAssignment->save();

    //                     $userDetails = User::where('id', $writer)->first();
    //                     if ($userDetails) {
    //                         try {
    //                             Mail::to($userDetails->email_address)->send(new AssignmentNotificationMail([
    //                                 'name' => $userDetails->employee_name,
    //                                 'role' => 'writer',
    //                                 'project_id' => $customId,
    //                                 // 'title' => $details->title,
    //                                 // 'duration' => $details->projectduration,
    //                                 // 'unit' => $details->$durationUnit ?? null, // prevent undefined property error
    //                             ]));

    //                             Log::info("Email sent to writer ({$userDetails->email_address}).");
    //                         } catch (\Exception $e) {
    //                             Log::error('Failed to send email to writer: '.$e->getMessage());
    //                         }
    //                     } else {
    //                         Log::warning("User not found for writer ID: {$user['writer']}");
    //                     }
    //                 } else {
    //                     // Insert new record
    //                     $assigned_d = new ProjectAssignDetails;
    //                     $assigned_d->project_id = $details->id;
    //                     $assigned_d->assign_user = $writer;
    //                     $assigned_d->assign_date = $user['writerDate'];
    //                     $assigned_d->status = $user['writerStatus'];
    //                     $assigned_d->status_date = $user['writerStatusDate'];
    //                     $assigned_d->project_duration = $user['writerprojectduration'];
    //                     $assigned_d->comments = $user['writer_comment'];
    //                     $assigned_d->type = 'writer';
    //                     $assigned_d->created_by = $request->created_by;
    //                     $assigned_d->save();

    //                     $secondWriter = ProjectAssignDetails::where('project_id', $details->id)
    //                         ->where('type', 'writer')
    //                         ->select('type')
    //                         ->get();
    //                     $userDetails = User::where('id', $writer)->first();
    //                     $createdByUser = User::where('id', $request->created_by)->first();
    //                     $creator = $createdByUser?->createdByUser?->name ?? 'Admin';
    //                     if (count($secondWriter) > 1) {
    //                         $activity = new ProjectActivity;
    //                         $activity->project_id = $details->id;
    //                         $activity->activity = 'Project assigned to '.$userDetails->employee_name.' (2nd writer)  by ' . $creator;
    //                         $activity->role = $creator;
    //                         $activity->created_by = $request->created_by;
    //                         $activity->created_date = now();
    //                         $activity->save();
    //                     }
    //                 }

    //                 $userDetails = User::find($writer);

    //                 // $activity = new ProjectActivity;
    //                 // $activity->project_id = $details->id;
    //                 // $activity->activity = 'Project assigned to ' . $userDetails->employee_name;
    //                 // $activity->created_by = $request->created_by;
    //                 // $activity->created_date = now();
    //                 // $activity->save();

    //                 $existingStatus = ProjectStatus::where('project_id', $details->id)
    //                     ->where('assign_id', $writer)
    //                     ->first();

    //                 if (! $existingStatus) {
    //                     ProjectStatus::create([
    //                         'project_id' => $details->id,
    //                         'assign_id' => $writer,
    //                     ]);
    //                 }

    //                 $existingWriterLog = ProjectLogs::where('project_id', $details->id)
    //                     ->where('employee_id', $writer)
    //                     ->where('status_type', 'writer')
    //                     ->latest()
    //                     ->first();

    //                 // if (!$existingWriterLog) {
    //                 ProjectLogs::create([
    //                     'project_id' => $details->id,
    //                     'employee_id' => $writer,
    //                     'assigned_date' => $user['writerDate'] ?? null,
    //                     'status' => $user['writerStatus'] ?? null,
    //                     'status_date' => $user['writerStatusDate'] ?? null,
    //                     'status_type' => 'writer',
    //                     'assign_preview_id' => optional(ProjectLogs::where('project_id', $details->id)
    //                         ->where('employee_id', $writer)
    //                         ->where('status_type', 'writer')
    //                         ->latest()
    //                         ->first())->id,
    //                     'created_by' => $request->created_by,
    //                     'created_date' => date('Y-m-d H:i:s'),
    //                 ]);
    //             }
    //         }
    //         if (is_array($request->reviewer) && ! empty($request->reviewer)) {
    //             foreach ($request->reviewer as $user) {
    //                 $reviewer = $user['reviewer'] ?? null;
    //                 $reviewerid = $user['reviewerid'] ?? null;
    //                 $existingAssignment = ProjectAssignDetails::where('project_id', $details->id)
    //                     ->where('id', $reviewerid)
    //                     ->where('type', 'reviewer')
    //                     ->first();
    //                 $existingAssignment2 = ProjectAssignDetails::where('project_id', $details->id)
    //                     ->where('status', 'correction')
    //                     ->where('type', 'team_coordinator')
    //                     // ->where('assign_user', $statistican)
    //                     // ->where('type_sme', 'reviewer')
    //                     ->first();

    //                 if ($existingAssignment2) {
    //                     $existingAssignment2->assign_user = $reviewer;
    //                     $existingAssignment2->assign_date = $user['reviewerAssignedDate'];
    //                     $existingAssignment2->status = $user['reviewerStatus'];
    //                     $existingAssignment2->status_date = $user['reviewerStatusDate'];
    //                     $existingAssignment2->project_duration = $user['reviewerProjectDuration'];
    //                     $existingAssignment2->comments = $user['reviewer_comment'] ?? '';
    //                     $existingAssignment2->save();
    //                 }

    //                 if ($existingAssignment) {
    //                     $existingAssignment->assign_user = $reviewer;
    //                     $existingAssignment->assign_date = $user['reviewerAssignedDate'];
    //                     $existingAssignment->status = $user['reviewerStatus'];
    //                     $existingAssignment->status_date = $user['reviewerStatusDate'];
    //                     $existingAssignment->project_duration = $user['reviewerProjectDuration'];
    //                     $existingAssignment->comments = $user['reviewer_comment'] ?? '';
    //                     $existingAssignment->save();

    //                     $userDetails = User::where('id', $reviewer)->first();
    //                     if ($userDetails) {
    //                         try {
    //                             Mail::to($userDetails->email_address)->send(new AssignmentNotificationMail([
    //                                 'name' => $userDetails->employee_name,
    //                                 'role' => 'reviewer',
    //                                 'project_id' => $customId,
    //                                 // 'title' => $details->title,
    //                                 // 'duration' => $details->projectduration,
    //                                 // 'unit' => $details->$durationUnit ?? null, // prevent undefined property error
    //                             ]));

    //                             Log::info("Email sent to reviewer ({$userDetails->email_address}).");
    //                         } catch (\Exception $e) {
    //                             Log::error('Failed to send email to reviewer: '.$e->getMessage());
    //                         }
    //                     } else {
    //                         Log::warning("User not found for reviewer ID: {$user['reviewer']}");
    //                     }
    //                 } else {
    //                     // Insert new record
    //                     $assigned_d = new ProjectAssignDetails;
    //                     $assigned_d->project_id = $details->id;
    //                     $assigned_d->assign_user = $user['reviewer'];
    //                     $assigned_d->assign_date = $user['reviewerAssignedDate'];
    //                     $assigned_d->status = $user['reviewerStatus'];
    //                     $assigned_d->status_date = $user['reviewerStatusDate'];
    //                     $assigned_d->project_duration = $user['reviewerProjectDuration'];
    //                     $assigned_d->comments = $user['reviewer_comment'];
    //                     $assigned_d->type = 'reviewer';
    //                     $assigned_d->created_by = $request->created_by;
    //                     $assigned_d->save();

    //                     $secondReviewer = ProjectAssignDetails::where('project_id', $details->id)
    //                         ->where('type', 'reviewer')
    //                         ->select('type')
    //                         ->get();
    //                     $userDetails = User::where('id', $user['reviewer'])->first();
    //                     $createdByUser = User::where('id', $request->created_by)->first();
    //                     $creator = $createdByUser?->createdByUser?->name ?? 'Admin';
    //                     if (count($secondReviewer) > 1) {
    //                         $activity = new ProjectActivity;
    //                         $activity->project_id = $details->id;
    //                         $activity->activity = 'Project assigned to '.$userDetails->employee_name.' (2nd reviewer)  by ' . $creator;
    //                         $activity->role = $creator;
    //                         $activity->created_by = $request->created_by;
    //                         $activity->created_date = now();
    //                         $activity->save();
    //                     }
    //                 }

    //                 $userDetails = User::find($user['reviewer']);

    //                 // $activity = new ProjectActivity;
    //                 // $activity->project_id = $details->id;
    //                 // $activity->activity = 'Project assigned to ' . $userDetails->employee_name;
    //                 // $activity->created_by = $request->created_by;
    //                 // $activity->created_date = now();
    //                 // $activity->save();

    //                 $existingAssignment = ProjectStatus::where('project_id', $details->id)
    //                     ->where('assign_id', $user['reviewer'])
    //                     ->first();

    //                 if ($existingAssignment) {
    //                     Log::info('check data');
    //                 } else {
    //                     ProjectStatus::create([
    //                         'project_id' => $details->id,
    //                         'assign_id' => $user['reviewer'],
    //                     ]);
    //                 }

    //                 $existingWriterLog = ProjectLogs::where('project_id', $details->id)
    //                     ->where('employee_id', $user['reviewer'])
    //                     ->where('status_type', 'reviewer')
    //                     ->latest()
    //                     ->first();

    //                 // if (!$existingWriterLog) {
    //                 ProjectLogs::create([
    //                     'project_id' => $details->id,
    //                     'employee_id' => $user['reviewer'],
    //                     'assigned_date' => $user['reviewerAssignedDate'],
    //                     'status' => $user['reviewerStatus'],
    //                     'status_date' => $user['reviewerStatusDate'],
    //                     'status_type' => 'reviewer',
    //                     // 'assing_preview_id' => $existingWriterLog->id,
    //                     'assign_preview_id' => optional(ProjectLogs::where('project_id', $details->id)
    //                         ->where('employee_id', $user['reviewer'])
    //                         ->where('status_type', 'reviewer')
    //                         ->latest()
    //                         ->first())->id,
    //                     'created_by' => $request->created_by,
    //                     'created_date' => date('Y-m-d H:i:s'),
    //                 ]);
    //             }
    //         }

    //         if (is_array($request->statistican) && ! empty($request->statistican)) {
    //             foreach ($request->statistican as $user) {
    //                 $statistican = $user['statistican'] ?? null;
    //                 $statisticanid = $user['statisticanid'] ?? null;
    //                 $existingAssignment = ProjectAssignDetails::where('project_id', $details->id)
    //                     ->where('id', $statisticanid)
    //                     ->where('type', 'statistican')
    //                     ->first();
    //                 $existingAssignment1 = ProjectAssignDetails::where('project_id', $details->id)
    //                     ->where('status', 'correction')
    //                     ->where('type', 'team_coordinator')
    //                     // ->where('assign_user', $statistican)
    //                     ->where('type_sme', 'statistican')
    //                     ->first();

    //                 if ($existingAssignment1) {
    //                     $existingAssignment1->assign_user = $statistican;
    //                     $existingAssignment1->assign_date = $user['statisticanAssignedDate'];
    //                     $existingAssignment1->status = $user['statisticanStatus'];
    //                     $existingAssignment1->status_date = $user['statisticanStatusDate'];
    //                     $existingAssignment1->project_duration = $user['statisticanProjectDuration'];
    //                     $existingAssignment1->comments = $user['statistican_comment'] ?? '';
    //                     $existingAssignment1->save();
    //                 }

    //                 if ($existingAssignment) {
    //                     $existingAssignment->assign_user = $statistican;
    //                     $existingAssignment->assign_date = $user['statisticanAssignedDate'];
    //                     $existingAssignment->status = $user['statisticanStatus'];
    //                     $existingAssignment->status_date = $user['statisticanStatusDate'];
    //                     $existingAssignment->project_duration = $user['statisticanProjectDuration'];
    //                     $existingAssignment->comments = $user['statistican_comment'] ?? '';
    //                     $existingAssignment->save();

    //                     $userDetails = User::where('id', $statistican)->first();
    //                     if ($userDetails) {
    //                         try {
    //                             Mail::to($userDetails->email_address)->send(new AssignmentNotificationMail([
    //                                 'name' => $userDetails->employee_name,
    //                                 'role' => 'statistican',
    //                                 'project_id' => $customId,
    //                                 // 'title' => $details->title,
    //                                 // 'duration' => $details->projectduration,
    //                                 // 'unit' => $details->$durationUnit ?? null, // prevent undefined property error
    //                             ]));

    //                             Log::info("Email sent to statistican ({$userDetails->email_address}).");
    //                         } catch (\Exception $e) {
    //                             Log::error('Failed to send email to statistican: '.$e->getMessage());
    //                         }
    //                     } else {
    //                         Log::warning("User not found for statistican ID: {$user['statistican']}");
    //                     }
    //                 } else {
    //                     // Insert new record
    //                     $assigned_d = new ProjectAssignDetails;
    //                     $assigned_d->project_id = $details->id;
    //                     $assigned_d->assign_user = $user['statistican'];
    //                     $assigned_d->assign_date = $user['statisticanAssignedDate'];
    //                     $assigned_d->status = $user['statisticanStatus'];
    //                     $assigned_d->status_date = $user['statisticanStatusDate'];
    //                     $assigned_d->project_duration = $user['statisticanProjectDuration'];
    //                     $assigned_d->comments = $user['statistican_comment'];
    //                     $assigned_d->type = 'statistican';
    //                     $assigned_d->created_by = $request->created_by;
    //                     $assigned_d->save();
    //                 }

    //                 $userDetails = User::find($user['statistican']);

    //                 // $activity = new ProjectActivity;
    //                 // $activity->project_id = $details->id;
    //                 // $activity->activity = 'Project assigned to ' . $userDetails->employee_name;
    //                 // $activity->created_by = $request->created_by;
    //                 // $activity->created_date = now();
    //                 // $activity->save();

    //                 $existingAssignment = ProjectStatus::where('project_id', $details->id)
    //                     ->where('assign_id', $user['statistican'])
    //                     ->first();

    //                 if ($existingAssignment) {
    //                     Log::info('check data');
    //                 } else {
    //                     ProjectStatus::create([
    //                         'project_id' => $details->id,
    //                         'assign_id' => $user['statistican'],
    //                     ]);
    //                 }

    //                 $existingWriterLog = ProjectLogs::where('project_id', $details->id)
    //                     ->where('employee_id', $user['statistican'])
    //                     ->where('status_type', 'statistican')
    //                     ->latest()
    //                     ->first();

    //                 // if (!$existingWriterLog) {
    //                 ProjectLogs::create([
    //                     'project_id' => $details->id,
    //                     'employee_id' => $user['statistican'],
    //                     'assigned_date' => $user['statisticanAssignedDate'],
    //                     'status' => $user['statisticanStatus'],
    //                     'status_date' => $user['statisticanStatusDate'],
    //                     'status_type' => 'statistican',
    //                     'assing_preview_id' => optional(ProjectLogs::where('project_id', $details->id)
    //                         ->where('employee_id', $user['statistican'])
    //                         ->where('status_type', 'statistican')
    //                         ->latest()
    //                         ->first())->id,
    //                     'created_by' => $request->created_by,
    //                     'created_date' => date('Y-m-d H:i:s'),
    //                 ]);
    //             }
    //         }
    //         if (is_array($request->journal) && ! empty($request->journal)) {
    //             foreach ($request->journal as $user) {
    //                 $journal = $user['journal'] ?? null;
    //                 $journalid = $user['journalid'] ?? null;

    //                 $existingAssignment = ProjectAssignDetails::where('project_id', $details->id)
    //                     ->where('id', $journalid)
    //                     ->where('type', 'publication_manager')
    //                     ->first();

    //                 if ($existingAssignment) {

    //                     $existingAssignment->assign_user = $journal;
    //                     $existingAssignment->assign_date = $user['journalAssignedDate'];
    //                     $existingAssignment->status = $user['journalStatus'];
    //                     $existingAssignment->status_date = $user['journalStatusDate'];
    //                     $existingAssignment->project_duration = $user['journalProjectDuration'];
    //                     $existingAssignment->comments = $user['journal_comment'] ?? '';
    //                     $existingAssignment->type_of_article = $user['type_of_article'] ?? '';
    //                     $existingAssignment->review = $user['review'] ?? '';
    //                     $existingAssignment->save();
    //                 } else {
    //                     // Insert new record
    //                     $assigned_d = new ProjectAssignDetails;
    //                     $assigned_d->project_id = $details->id;
    //                     $assigned_d->assign_user = $user['journal'];
    //                     $assigned_d->assign_date = $user['journalAssignedDate'];
    //                     $assigned_d->status = $user['journalStatus'];
    //                     $assigned_d->status_date = $user['journalStatusDate'];
    //                     $assigned_d->project_duration = $user['journalProjectDuration'];
    //                     $assigned_d->comments = $user['journal_comment'] ?? '';
    //                     $assigned_d->type = 'publication_manager';
    //                     $assigned_d->type_of_article = $user['type_of_article'] ?? '';
    //                     $assigned_d->review = $user['review'] ?? '';
    //                     $assigned_d->created_by = $request->created_by;
    //                     $assigned_d->save();
    //                 }

    //                 $existingAssignment = ProjectStatus::where('project_id', $details->id)
    //                     ->where('assign_id', $user['journal'])
    //                     ->first();

    //                 if ($existingAssignment) {
    //                     Log::info('check data');
    //                 } else {
    //                     ProjectStatus::create([
    //                         'project_id' => $details->id,
    //                         'assign_id' => $user['journal'],
    //                     ]);
    //                 }

    //                 $existingWriterLog = ProjectLogs::where('project_id', $details->id)
    //                     ->where('employee_id', $user['journal'])
    //                     ->where('status_type', 'publication_manager')
    //                     ->latest()
    //                     ->first();

    //                 // if (!$existingWriterLog) {
    //                 ProjectLogs::create([
    //                     'project_id' => $details->id,
    //                     'employee_id' => $user['journal'],
    //                     'assigned_date' => $user['journalAssignedDate'],
    //                     'status' => $user['journalStatus'],
    //                     'status_date' => $user['journalStatusDate'],
    //                     'status_type' => 'publication_manager',
    //                     'assing_preview_id' => optional(ProjectLogs::where('project_id', $details->id)
    //                         ->where('employee_id', $user['journal'])
    //                         ->where('status_type', 'publication_manager')
    //                         ->latest()
    //                         ->first())->id,
    //                     'created_by' => $request->created_by,
    //                     'created_date' => date('Y-m-d H:i:s'),
    //                 ]);
    //             }
    //         }

    //         //latest update
    //         // if ($request->has('entryprocess_documents') && is_array($request->entryprocess_documents)) {

    //         //     $defaultSpecificOption = null;
    //         //     $entryprocessDocuments = [];

    //         //     foreach ($request->entryprocess_documents as $document) {
    //         //         $fileNames = [];

    //         //         // Initialize a new EntryDocument always
    //         //         $entryDocument = new EntryDocument();
    //         //         $entryDocument->entry_process_model_id = $details->id;
    //         //         $entryDocument->created_by = $request->created_by ?? '-';

    //         //         // Handle select_document (specificOption) if available
    //         //         if (isset($document['specificOption']) && is_array($document['specificOption']) && !empty($document['specificOption'])) {
    //         //             $defaultSpecificOption = $document['specificOption']; // set default
    //         //             $entryDocument->select_document = json_encode($document['specificOption'], JSON_UNESCAPED_UNICODE);
    //         //         } elseif ($defaultSpecificOption !== null) {
    //         //             $entryDocument->select_document = json_encode($defaultSpecificOption, JSON_UNESCAPED_UNICODE); // use default if available
    //         //         }

    //         //         $entryDocument->save();

    //         //         // Handle file uploads if present
    //         //         if (isset($document['file']) && is_array($document['file'])) {
    //         //             foreach ($document['file'] as $file) {
    //         //                 if (!empty($file)) {
    //         //                     $originalName = $file->getClientOriginalName();
    //         //                     $originalExtension = $file->getClientOriginalExtension();
    //         //                     $cleanedName = strtolower(preg_replace('/[^a-z0-9]+/i', '-', pathinfo($originalName, PATHINFO_FILENAME)));
    //         //                     $cleanedName = str_replace('_', '', $cleanedName);
    //         //                     $uniqueName = $cleanedName . '.' . $originalExtension;

    //         //                     $path = public_path('uploads');
    //         //                     if (!is_dir($path)) {
    //         //                         mkdir($path, 0775, true);
    //         //                     }

    //         //                     $file->move($path, $uniqueName);

    //         //                     // Save file record
    //         //                     $documentList = new EntryDocumentsList();
    //         //                     $documentList->document_id = $entryDocument->id;
    //         //                     $documentList->file = $uniqueName;
    //         //                     $documentList->original_name = $cleanedName . '.' . $originalExtension;
    //         //                     $documentList->save();

    //         //                     $fileNames[] = $uniqueName;
    //         //                 }
    //         //             }
    //         //         }

    //         //         // Response formatting
    //         //         $entryprocessDocuments[] = [
    //         //             'specificOption' => $entryDocument->select_document ? json_decode($entryDocument->select_document) : null,
    //         //             'file' => $fileNames
    //         //         ];
    //         //     }

    //         //     // Return a successful response
    //         //     return response()->json([
    //         //         'entryprocess_documents' => $entryprocessDocuments
    //         //     ], 200);
    //         // } else {
    //         //     return response()->json(['error' => 'Invalid input data'], 400);
    //         // }

    //         //new
    //         if ($request->has('entryprocess_documents') && is_array($request->entryprocess_documents)) {

    //             $defaultSpecificOption = null;
    //             $entryprocessDocuments = [];

    //             foreach ($request->entryprocess_documents as $document) {
    //                 $fileNames = [];

    //                 // Try to find an existing entry document
    //                 $entryDocument = EntryDocument::where('entry_process_model_id', $details->id)
    //                     ->when(isset($document['id']), function ($q) use ($document) {
    //                         $q->where('id', $document['id']); // if id is passed
    //                     })
    //                     ->first();

    //                 // If not found, create new
    //                 if (! $entryDocument) {
    //                     $entryDocument = new EntryDocument;
    //                     $entryDocument->entry_process_model_id = $details->id;
    //                     $entryDocument->created_by = $request->created_by ?? '-';
    //                 }

    //                 // Handle select_document (specificOption)
    //                 if (isset($document['specificOption']) && is_array($document['specificOption']) && ! empty($document['specificOption'])) {
    //                     $defaultSpecificOption = $document['specificOption'];
    //                     $entryDocument->select_document = json_encode($document['specificOption'], JSON_UNESCAPED_UNICODE);
    //                 } elseif ($defaultSpecificOption !== null) {
    //                     $entryDocument->select_document = json_encode($defaultSpecificOption, JSON_UNESCAPED_UNICODE);
    //                 }

    //                 $entryDocument->save();

    //                 // Handle file uploads (append new files)
    //                 if (isset($document['file']) && is_array($document['file'])) {
    //                     foreach ($document['file'] as $file) {
    //                         if (! empty($file)) {
    //                             $originalName = $file->getClientOriginalName();
    //                             $originalExtension = $file->getClientOriginalExtension();
    //                             $cleanedName = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($originalName, PATHINFO_FILENAME));
    //                             $cleanedName = str_replace('_', '', $cleanedName);
    //                             $uniqueName = $cleanedName.'.'.$originalExtension;

    //                             $path = public_path('uploads');
    //                             if (! is_dir($path)) {
    //                                 mkdir($path, 0775, true);
    //                             }

    //                             $file->move($path, $uniqueName);

    //                             // Save new file record
    //                             $documentList = new EntryDocumentsList;
    //                             $documentList->document_id = $entryDocument->id;
    //                             $documentList->file = $uniqueName;
    //                             $documentList->original_name = $cleanedName.'.'.$originalExtension;
    //                             $documentList->save();

    //                             $fileNames[] = $uniqueName;
    //                         }
    //                     }
    //                 }

    //                 // Response formatting
    //                 $entryprocessDocuments[] = [
    //                     'specificOption' => $entryDocument->select_document ? json_decode($entryDocument->select_document) : null,
    //                     'file' => $fileNames,
    //                 ];
    //             }

    //             return response()->json([
    //                 'entryprocess_documents' => $entryprocessDocuments,
    //             ], 200);
    //         } else {
    //             return response()->json(['error' => 'Invalid input data'], 400);
    //         }
    //     });
    // }

    private function ordinal($number)
    {
        $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];

        if (($number % 100) >= 11 && ($number % 100) <= 13) {
            return $number.'th';
        }

        return $number.$ends[$number % 10];
    }

    public function update(Request $request, $id)
    {

        $selectedOption = $request->type_of_work;
        $customId = $request->project_id;

        DB::transaction(function () use ($selectedOption, $request, &$customId, $id) {
            $details = EntryProcessModel::find($id);
            if (! $details) {
                throw new \Exception('Entry not found');
            }
            $roles = [];

            if (! empty($request->writer) && $details->writer !== $request->writer) {
                $roles['writer'] = 'Writer';
            }
            if (! empty($request->reviewer) && $details->reviewer !== $request->reviewer) {
                $roles['reviewer'] = 'Reviewer';
            }
            if (! empty($request->statistican) && $details->statistican !== $request->statistican) {
                $roles['statistican'] = 'Statistician';
            }

            $roles = [
                'writer' => 'Writer',
                'reviewer' => 'Reviewer',
                'statistican' => 'Statistician',
                'journal' => 'Publication Manager',
            ];

            foreach ($roles as $key => $role) {
                if (! empty($request->$key)) {
                    $userDetails = User::where('id', $request->$key)->first();

                    if ($userDetails) {
                        if (! empty($userDetails->email_address)) {
                            $durationKey = $key.'_project_duration';
                            $durationUnit = $key.'_duration_unit';
                            try {
                                Mail::to($userDetails->email_address)->send(new AssignmentNotificationMail([
                                    'name' => $userDetails->employee_name,
                                    'role' => $role,
                                    'project_id' => $customId,
                                    'title' => $details->title,
                                    'duration' => $details->$durationKey,
                                    'unit' => $details->$durationUnit,
                                ]));
                                Log::info("Email sent to {$role} ({$userDetails->email_address}).");
                            } catch (\Exception $e) {
                                Log::error("Failed to send email to {$role}: ".$e->getMessage());
                            }
                        } else {
                            Log::error("{$role} email is empty or invalid.");
                        }
                    } else {
                        Log::error("{$role} not found.");
                    }
                } else {
                    Log::error("No {$role} ID provided.");
                }
            }

            if ($details->project_id !== $request->project_id) {
                $duplicateProjectId = EntryProcessModel::where('project_id', $request->project_id)
                    ->where('id', '!=', $id)
                    ->where('is_deleted', 0)
                    ->exists();

                if ($duplicateProjectId) {
                    throw new \Exception("Project ID '{$request->project_id}' is already in use.");
                }
            }

            if ($details->type_of_work !== $selectedOption) {
                $lastEntry = EntryProcessModel::where('type_of_work', $selectedOption)
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

                $increment = $lastEntry ? (int) substr($lastEntry->project_id, strlen($selectedOption) + 1) + 1 : 1;
                $formattedIncrement = str_pad($increment, 3, '0', STR_PAD_LEFT);
                $customId = $selectedOption.'-'.$formattedIncrement;
            } else {
                $customId = $details->project_id;
            }

            $details->entry_date = $request->entry_date;
            $details->title = $request->title;
            $details->project_id = $customId;
            $details->type_of_work = $request->type_of_work;
            $details->others = $request->others;
            $details->client_name = $request->client_name;
            $details->email = $request->email;
            $details->contact_number = $request->contact_number;
            $details->institute = $request->institute;
            $details->department = $request->department;
            $details->profession = $request->profession;
            $details->budget = $request->budget;
            $details->process_status = $request->process_status;
            $details->process_date = $request->process_date;
            $details->hierarchy_level = $request->hierarchy_level;
            $details->comment_box = $request->comment_box;
            $details->else_project_manager = $request->else_project_manager;
            $details->status = $request->status ?? $details->status;
            $details->is_deleted = $request->is_deleted ?? $details->is_deleted;
            // $details->created_by = $request->created_by;
            $details->projectduration = $request->project_duration;
            $details->save();

            
            $created = User::with('createdByUser')->find($request->created_by);
            $creator = $created?->createdByUser?->name ?? 'Admin';
                // if ($details->process_status !== $request->process_status) {
                    ProjectActivity::firstOrCreate(
                        [
                            'project_id' => $details->id,
                            'activity' => 'Project process status marked as '.$request->process_status,
                            'role' => $creator,
                            'created_by' => $request->created_by,
                        ],
                        [
                            'created_date' => now(),
                        ]
                    );
                // }
            if ($request->created_by != '86') {

                ProjectActivity::firstOrCreate(
                    [
                        'project_id' => $details->id,
                        'activity' => 'Project has edited ',
                        'created_by' => $request->created_by,
                        'role' => $creator,
                    ],
                    [
                        'created_date' => now(),
                    ]
                );

            }

            if ($request->process_status === 'withdrawal') {

                $positions = [13, 14, 'Admin'];

                $users = User::whereIn('position', $positions)
                    ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                    ->get()
                    ->keyBy('position');

                $emails = [
                    'projectManager' => $users->get(13)?->email_address,
                    'teamManager' => $users->get(14)?->email_address,

                    'adminEmail' => $users->get('Admin')?->email_address,

                ];

                $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $request->created_by)->first();
                $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

                if (! empty($emails['projectManager']) && ! empty($emails['teamManager']) && ! empty($emails['adminEmail']) && ! empty($employeedetails->email_address)) {
                    try {
                        // Send email to writer with CC to others
                        Mail::to($emails['projectManager'], $emails['teamManager'])
                            ->cc($emails['adminEmail'])
                            ->send(new TaskCompleteEmail([
                                'projectManagerEmail' => $emails['projectManager'],
                                'teamManagerEmail' => $emails['teamManager'],
                                'adminEmail' => $emails['adminEmail'],

                                'employee_name' => $employeedetails->employee_name,
                                'role' => $employeedetails->createdByUser?->name,

                                'project_id' => $projectDetails->project_id,
                                'status' => $request->process_status,
                                'detail_name' => $request->process_status,
                            ]));

                        Log::info('Email sent to project manager and team manager');
                    } catch (\Exception $e) {
                        Log::error('Mail failed: '.$e->getMessage());
                    }
                }
            }

            //payment update
            if (! is_null($request->payment_status) && trim($request->payment_status) !== '') {
                Log::info('Entering payment_status condition', ['payment_status' => $request->payment_status]);
                $details_p = PaymentStatusModel::where('id', $request->payment_id)->first();

                if ($details_p) {
                    Log::info('Updating existing payment status', ['payment_id' => $request->payment_id]);
                    // $details_p->project_id = $details->id;
                    $details_p->payment_status = $request->payment_status;
                    $details_p->created_by = $request->created_by;
                    $details_p->save();

                    // $userDetails = User::find($writer);
                    // if ($request->created_by != '86') {
                    //     $activity = new ProjectActivity;
                    //     $activity->project_id = $details->id;
                    //     $activity->activity = 'Payment marked as '.$request->payment_status;
                    //     $activity->created_by = $request->created_by;
                    //     $activity->created_date = now();
                    //     $activity->save();
                    // }

                    // if ($request->created_by != '86') {

                    //     ProjectActivity::firstOrCreate(
                    //         [
                    //             'project_id' => $details->id,
                    //             'activity' => 'Payment marked as '.$request->payment_status,
                    //             'created_by' => $request->created_by,
                    //         ],
                    //         [
                    //             'created_date' => now(),
                    //         ]
                    //     );
                    // }

                    // $created = User::with('createdByUser')->find($request->created_by);

                    // $employee = $created?->employee_name ?? 'Mohamed Ali';
                    // $creator = $created?->createdByUser?->name ?? 'Admin';
                    // $activityText = "Payment marked as {$request->payment_status} by {$employee} ({$creator})";
                    // if ($request->created_by != 86) {

                    //     ProjectActivity::firstOrCreate(
                    //         [
                    //             'project_id' => $details->id,
                    //             'activity' => $activityText,
                    //             'role' => $creator,
                    //             'created_by' => $request->created_by,
                    //         ],
                    //         [
                    //             'created_date' => now(),
                    //         ]
                    //     );
                    // }

                    if ($details_p->payment_status !== $request->payment_status) {

                        $created = User::with('createdByUser')->find($request->created_by);

                        $employee = $created?->employee_name ?? 'Mohamed Ali';
                        $creator = $created?->createdByUser?->name ?? 'Admin';

                        $activityText = "Payment marked as {$request->payment_status} by {$employee} ({$creator})";

                        // if ($request->created_by != 86) {
                        //     ProjectActivity::firstOrCreate(
                        //         [
                        //             'project_id' => $details->id,
                        //             'activity' => $activityText,
                        //             'role' => $creator,
                        //             'created_by' => $request->created_by,
                        //         ],
                        //         [
                        //             'created_date' => now(),
                        //         ]
                        //     );
                        // }
                    }

                } else {
                    Log::info('Creating new payment status', ['project_id' => $details->id]);

                    $details_p = new PaymentStatusModel;
                    $details_p->project_id = $details->id;
                    $details_p->payment_status = $request->payment_status;
                    $details_p->created_by = $request->created_by;
                    $details_p->save();
                }
                if (! empty($request->payment_details) && is_array($request->payment_details)) {
                    foreach ($request->payment_details as $pay) {
                        // Check if the payment_detail already exists by its ID
                        if (isset($pay['id']) && $pay['id']) {
                            // If the ID exists, update the record
                            $paymentDetails = PaymentDetails::find($pay['id']);

                            // Ensure the payment detail exists before updating
                            if ($paymentDetails) {
                                $paymentDetails->payment = $pay['payment'] ?? $paymentDetails->payment;
                                $paymentDetails->payment_date = $pay['payment_date'] ?? $paymentDetails->payment_date;

                                // Only update payment_type if it was previously null
                                if ($paymentDetails->payment_type === null) {
                                    $paymentDetails->payment_type = $request->payment_status ?? $paymentDetails->payment_type;
                                }

                                $paymentDetails->save();
                            }
                        } else {
                            // If no ID, create a new payment detail record
                            $existingPayment = PaymentDetails::where('payment_id', $details_p->id)
                                ->where('payment', $pay['payment'] ?? null)
                                ->where('payment_date', $pay['payment_date'] ?? null)
                                ->where('payment_type', $request->payment_status ?? null)
                                ->first();

                            if (! $existingPayment) {
                                $paymentDetails = new PaymentDetails;
                                $paymentDetails->payment_id = $details_p->id;
                                $paymentDetails->payment = $pay['payment'] ?? null;
                                $paymentDetails->payment_date = $pay['payment_date'] ?? null;
                                $paymentDetails->payment_type = $request->payment_status ?? null;
                                $paymentDetails->save();
                            }
                        }
                    }
                } else {
                    Log::error('payment_details is either null or not an array', ['request_data' => $request->all()]);
                }

                // Add new payment details if provided
                if ($request->has('new_payment_details') && is_array($request->new_payment_details)) {
                    foreach ($request->new_payment_details as $newPay) {
                        $existingNewPayment = PaymentDetails::where('payment_id', $details_p->id)
                            ->where('payment', $newPay['payment'] ?? null)
                            ->where('payment_date', $newPay['payment_date'] ?? null)
                            ->where('payment_type', $request->payment_status ?? null)
                            ->first();

                        if (! $existingNewPayment) {
                            $newPaymentDetails = new PaymentDetails;
                            $newPaymentDetails->payment_id = $details_p->id;
                            $newPaymentDetails->payment = $newPay['payment'] ?? null;
                            $newPaymentDetails->payment_date = $newPay['payment_date'] ?? null;
                            $newPaymentDetails->payment_type = $request->payment_status ?? null;
                            $newPaymentDetails->save();
                        }
                    }
                }

                $paymentLog = PaymentLogs::where('project_id', $request->project_id)
                    ->where('payment_id', $details_p->id)
                    ->where('payment_status', $request->payment_status)
                    ->first();

                if ($paymentLog) {
                    $paymentLog->created_date = date('Y-m-d H:i:s'); // Current timestamp
                    $paymentLog->save();
                } else {
                    PaymentLogs::create([
                        'project_id' => $details->id,
                        'payment_id' => $details_p->id,
                        'payment_status' => $request->payment_status,
                        'created_by' => $request->created_by,
                        'created_date' => date('Y-m-d H:i:s'), // Current timestamp
                    ]);
                }
            }

            if (! empty($request->payment_freelancer) && is_array($request->payment_freelancer)) {

                foreach ($request->payment_freelancer as $pay) {

                    // UPDATE existing payment detail
                    if (! empty($pay['id'])) {

                        $freelancerDetails = EmployeePaymentDetails::find($pay['id']);

                        if ($freelancerDetails) {
                            $freelancerDetails->employee_id = $pay['employee_id'] ?? $freelancerDetails->employee_id;
                            $freelancerDetails->payment = $pay['payment'] ?? $freelancerDetails->payment;
                            $freelancerDetails->payment_date = $pay['date'] ?? $freelancerDetails->payment_date;
                            $freelancerDetails->status = $pay['status'] ?? $freelancerDetails->status;
                            $freelancerDetails->type = $pay['type'] ?? $freelancerDetails->type;

                            $freelancerDetails->save();
                        }

                    }
                    // CREATE new payment detail
                    else {

                        $paymentDetails = new EmployeePaymentDetails;
                        $paymentDetails->project_id = $details->id;
                        $paymentDetails->payment_id = $details_p->id;
                        $paymentDetails->employee_id = $pay['employee_id'] ?? null;
                        $paymentDetails->payment = $pay['payment'] ?? null;
                        $paymentDetails->status = $pay['status'] ?? null;
                        $paymentDetails->payment_date = $pay['date'] ?? now();
                        $paymentDetails->type = $pay['type'] ?? '';
                        $paymentDetails->created_date = $pay['payment_date'] ?? now();

                        $paymentDetails->save();
                    }
                }
            }

            //payment end

            // $activity = new ProjectActivity;
            // $activity->project_id = $details->id;
            // $activity->activity = 'updated successfully';
            // $activity->created_by = $request->created_by;
            // $activity->created_date = date('Y-m-d H:i:s');
            // $activity->save();

            // ProjectActivity::firstOrCreate(
            //     [
            //         'project_id' => $details->id,
            //         'activity' => 'updated successfully',
            //         'created_by' => $request->created_by,
            //     ],
            //     [
            //         'created_date' => now(),
            //     ]
            // );

            if (! empty($request->process_status)) {
                $existingStatus = ProjectViewStatus::where('project_id', $details->id)
                    ->where('project_status', $request->process_status)
                    ->where('created_by', $request->created_by)
                    ->latest()
                    ->first();

                if (! $existingStatus) {
                    $comments = new ProjectViewStatus;
                    $comments->project_id = $details->id;
                    $comments->project_status = $request->process_status;
                    $comments->created_by = $request->created_by;
                    $comments->created_date = date('Y-m-d H:i:s');
                    $comments->save();
                }
            }

            if (! empty($request->comment_box)) {

                $existingComment = Commends::where('project_id', $details->id)
                    ->where('assignee', $request->created_by)
                    ->first();

                if ($existingComment) {
                    $existingComment->commend_box = $request->comment_box ?? $existingComment->commend_box;
                    $existingComment->save();
                } else {
                    $comments = new Commends;
                    $comments->project_id = $details->id;
                    $comments->commend_box = $request->comment_box ?? null;
                    $comments->created_by = $request->created_by;
                    $comments->created_date = date('Y-m-d H:i:s');
                    $comments->assignee = $request->created_by;
                    $comments->save();
                }
            }

            //project status
            if (! empty($request->writer) && is_array($request->writer)) {
                foreach ($request->writer as $user) {
                    Log::info('check data1');
                    $writer = $user['writer'] ?? null;
                    $writerid = $user['writerid'] ?? null;

                    $existingAssignment = ProjectAssignDetails::where('project_id', $details->id)
                        ->where('id', $writerid)
                        ->where('type', 'writer')
                        ->first();
                    if ($existingAssignment && $existingAssignment->assign_user !== $writer) {
                        $existingEmployeeFreelancer = EmployeePaymentDetails::where('project_id', $details->id)
                            ->where('employee_id', $existingAssignment->assign_user)
                            ->where('type', 'writer')
                            ->first();

                        if ($existingEmployeeFreelancer) {
                            $existingEmployeeFreelancer->employee_id = $writer;
                            $existingEmployeeFreelancer->type = 'writer';
                            $existingEmployeeFreelancer->save();
                        }
                    }

                    $existingAssignment3 = ProjectAssignDetails::where('project_id', $details->id)
                        ->where('status', 'correction')
                        ->where('type', 'team_coordinator')
                        ->whereNotNull('type_sme')
                        // ->where('assign_user', $statistican)
                        ->first();

                    if ($existingAssignment3) {
                        $existingAssignment3->assign_user = $writer;
                        $existingAssignment3->assign_date = $user['writerDate'] ?? $existingAssignment3->assign_date;
                        $existingAssignment3->status = $user['writerStatus'] ?? $existingAssignment->status;
                        $existingAssignment3->status_date = $user['writerStatusDate'] ?? $existingAssignment3->status_date;
                        $existingAssignment3->project_duration = $user['writerprojectduration'] ?? $existingAssignment3->project_duration;
                        $existingAssignment3->comments = $user['writer_comment'] ?? $existingAssignment3->comments;
                        $existingAssignment3->save();
                    }

                    if ($existingAssignment) {
                        // Update existing record'
                        $existingAssignment->assign_user = $writer;
                        $existingAssignment->assign_date = $user['writerDate'] ?? $existingAssignment->assign_date;
                        $existingAssignment->status = $user['writerStatus'] ?? $existingAssignment->status;
                        $existingAssignment->status_date = $user['writerStatusDate'] ?? $existingAssignment->status_date;
                        $existingAssignment->project_duration = $user['writerprojectduration'] ?? $existingAssignment->project_duration;
                        $existingAssignment->comments = $user['writer_comment'] ?? $existingAssignment->comments;
                        $existingAssignment->save();

                      

                        $userDetails = User::where('id', $writer)->first();
                        if ($userDetails) {
                            try {
                                Mail::to($userDetails->email_address)->send(new AssignmentNotificationMail([
                                    'name' => $userDetails->employee_name,
                                    'role' => 'writer',
                                    'project_id' => $customId,
                                    // 'title' => $details->title,
                                    // 'duration' => $details->projectduration,
                                    // 'unit' => $details->$durationUnit ?? null, // prevent undefined property error
                                ]));

                                Log::info("Email sent to writer ({$userDetails->email_address}).");
                            } catch (\Exception $e) {
                                Log::error('Failed to send email to writer: '.$e->getMessage());
                            }
                        } else {
                            Log::warning("User not found for writer ID: {$user['writer']}");
                        }
                    } else {
                        // Insert new record
                        $assigned_d = new ProjectAssignDetails;
                        $assigned_d->project_id = $details->id;
                        $assigned_d->assign_user = $writer;
                        $assigned_d->assign_date = $user['writerDate'];
                        $assigned_d->status = $user['writerStatus'];
                        $assigned_d->status_date = $user['writerStatusDate'];
                        $assigned_d->project_duration = $user['writerprojectduration'];
                        $assigned_d->comments = $user['writer_comment'];
                        $assigned_d->type = 'writer';
                        $assigned_d->created_by = $request->created_by;
                        $assigned_d->save();

                        // function ordinal($number)
                        // {
                        //     $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];

                        //     if (($number % 100) >= 11 && ($number % 100) <= 13) {
                        //         return $number.'th';
                        //     }

                        //     return $number.$ends[$number % 10];
                        // }

                        $secondWriter = ProjectAssignDetails::where('project_id', $details->id)
                            ->where('type', 'writer')
                            ->select('type')
                            ->get();
                        $finalValue = $secondWriter->count();
                        $userDetails = User::where('id', $writer)->first();
                        $createdByUser = User::where('id', $request->created_by)->first();
                        $creator = $createdByUser?->createdByUser?->name ?? 'Admin';
                        $position = $this->ordinal($finalValue);
                        // if (count($secondWriter) > 1) {
                        $activity = new ProjectActivity;
                        $activity->project_id = $details->id;
                        $activity->activity = 'Project assigned to '
                            .$userDetails->employee_name
                            .' as the '.$position.' writer by '
                            .$creator;
                        $activity->role = $creator;
                        $activity->created_by = $request->created_by;
                        $activity->created_date = now();
                        $activity->save();
                        // }
                    }

                    $userDetails = User::find($writer);

                    // $activity = new ProjectActivity;
                    // $activity->project_id = $details->id;
                    // $activity->activity = 'Project assigned to ' . $userDetails->employee_name;
                    // $activity->created_by = $request->created_by;
                    // $activity->created_date = now();
                    // $activity->save();

                    $existingStatus = ProjectStatus::where('project_id', $details->id)
                        ->where('assign_id', $writer)
                        ->first();

                    if (! $existingStatus) {
                        ProjectStatus::create([
                            'project_id' => $details->id,
                            'assign_id' => $writer,
                        ]);
                    }

                    $existingWriterLog = ProjectLogs::where('project_id', $details->id)
                        ->where('employee_id', $writer)
                        ->where('status_type', 'writer')
                        ->latest()
                        ->first();

                    // if (!$existingWriterLog) {
                    ProjectLogs::create([
                        'project_id' => $details->id,
                        'employee_id' => $writer,
                        'assigned_date' => $user['writerDate'] ?? null,
                        'status' => $user['writerStatus'] ?? null,
                        'status_date' => $user['writerStatusDate'] ?? null,
                        'status_type' => 'writer',
                        'assign_preview_id' => optional(ProjectLogs::where('project_id', $details->id)
                            ->where('employee_id', $writer)
                            ->where('status_type', 'writer')
                            ->latest()
                            ->first())->id,
                        'created_by' => $request->created_by,
                        'created_date' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            if (is_array($request->reviewer) && ! empty($request->reviewer)) {
                foreach ($request->reviewer as $user) {
                    $reviewer = $user['reviewer'] ?? null;
                    $reviewerid = $user['reviewerid'] ?? null;
                    $existingAssignment = ProjectAssignDetails::where('project_id', $details->id)
                        ->where('id', $reviewerid)
                        ->where('type', 'reviewer')
                        ->first();

                    if ($existingAssignment && $existingAssignment->assign_user !== $reviewer) {
                        $existingEmployeeFreelancer = EmployeePaymentDetails::where('project_id', $details->id)
                            ->where('employee_id', $existingAssignment->assign_user)
                            ->where('type', 'reviewer')
                            ->first();

                        if ($existingEmployeeFreelancer) {
                            $existingEmployeeFreelancer->employee_id = $reviewer;
                            $existingEmployeeFreelancer->type = 'reviewer';
                            $existingEmployeeFreelancer->save();
                        }
                    }
                    $existingAssignment2 = ProjectAssignDetails::where('project_id', $details->id)
                        ->where('status', 'correction')
                        ->where('type', 'team_coordinator')
                        ->whereNotNull('type_sme')
                        ->first();

                    if ($existingAssignment2) {
                        $existingAssignment2->assign_user = $reviewer;
                        $existingAssignment2->assign_date = $user['reviewerAssignedDate'];
                        $existingAssignment2->status = $user['reviewerStatus'];
                        $existingAssignment2->status_date = $user['reviewerStatusDate'];
                        $existingAssignment2->project_duration = $user['reviewerProjectDuration'];
                        $existingAssignment2->comments = $user['reviewer_comment'] ?? '';
                        $existingAssignment2->save();
                    }

                    if ($existingAssignment) {
                        $existingAssignment->assign_user = $reviewer;
                        $existingAssignment->assign_date = $user['reviewerAssignedDate'];
                        $existingAssignment->status = $user['reviewerStatus'];
                        $existingAssignment->status_date = $user['reviewerStatusDate'];
                        $existingAssignment->project_duration = $user['reviewerProjectDuration'];
                        $existingAssignment->comments = $user['reviewer_comment'] ?? '';
                        $existingAssignment->save();

                        $userDetails = User::where('id', $reviewer)->first();
                        if ($userDetails) {
                            try {
                                Mail::to($userDetails->email_address)->send(new AssignmentNotificationMail([
                                    'name' => $userDetails->employee_name,
                                    'role' => 'reviewer',
                                    'project_id' => $customId,
                                    // 'title' => $details->title,
                                    // 'duration' => $details->projectduration,
                                    // 'unit' => $details->$durationUnit ?? null, // prevent undefined property error
                                ]));

                                Log::info("Email sent to reviewer ({$userDetails->email_address}).");
                            } catch (\Exception $e) {
                                Log::error('Failed to send email to reviewer: '.$e->getMessage());
                            }
                        } else {
                            Log::warning("User not found for reviewer ID: {$user['reviewer']}");
                        }
                    } else {
                        // Insert new record
                        $assigned_d = new ProjectAssignDetails;
                        $assigned_d->project_id = $details->id;
                        $assigned_d->assign_user = $user['reviewer'];
                        $assigned_d->assign_date = $user['reviewerAssignedDate'];
                        $assigned_d->status = $user['reviewerStatus'];
                        $assigned_d->status_date = $user['reviewerStatusDate'];
                        $assigned_d->project_duration = $user['reviewerProjectDuration'];
                        $assigned_d->comments = $user['reviewer_comment'];
                        $assigned_d->type = 'reviewer';
                        $assigned_d->created_by = $request->created_by;
                        $assigned_d->save();
                        // function ordinal($number)
                        // {
                        //     $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];

                        //     if (($number % 100) >= 11 && ($number % 100) <= 13) {
                        //         return $number.'th';
                        //     }

                        //     return $number.$ends[$number % 10];
                        // }
                        $secondReviewer = ProjectAssignDetails::where('project_id', $details->id)
                            ->where('type', 'reviewer')
                            ->select('type')
                            ->get();
                        $finalValue = $secondReviewer->count();
                        $userDetails = User::where('id', $user['reviewer'])->first();
                        $createdByUser = User::where('id', $request->created_by)->first();
                        $creator = $createdByUser?->createdByUser?->name ?? 'Admin';
                        $position = $this->ordinal($finalValue);
                        $activity = new ProjectActivity;
                        $activity->project_id = $details->id;
                        $activity->activity = 'Project assigned to '
                            .$userDetails->employee_name
                            .' as the '.$position.' reviewer by '
                            .$creator;
                        $activity->role = $creator;
                        $activity->created_by = $request->created_by;
                        $activity->created_date = now();
                        $activity->save();
                    }

                    $userDetails = User::find($user['reviewer']);

                    // $activity = new ProjectActivity;
                    // $activity->project_id = $details->id;
                    // $activity->activity = 'Project assigned to ' . $userDetails->employee_name;
                    // $activity->created_by = $request->created_by;
                    // $activity->created_date = now();
                    // $activity->save();

                    $existingAssignment = ProjectStatus::where('project_id', $details->id)
                        ->where('assign_id', $user['reviewer'])
                        ->first();

                    if ($existingAssignment) {
                        Log::info('check data');
                    } else {
                        ProjectStatus::create([
                            'project_id' => $details->id,
                            'assign_id' => $user['reviewer'],
                        ]);
                    }

                    $existingWriterLog = ProjectLogs::where('project_id', $details->id)
                        ->where('employee_id', $user['reviewer'])
                        ->where('status_type', 'reviewer')
                        ->latest()
                        ->first();

                    // if (!$existingWriterLog) {
                    ProjectLogs::create([
                        'project_id' => $details->id,
                        'employee_id' => $user['reviewer'],
                        'assigned_date' => $user['reviewerAssignedDate'],
                        'status' => $user['reviewerStatus'],
                        'status_date' => $user['reviewerStatusDate'],
                        'status_type' => 'reviewer',
                        // 'assing_preview_id' => $existingWriterLog->id,
                        'assign_preview_id' => optional(ProjectLogs::where('project_id', $details->id)
                            ->where('employee_id', $user['reviewer'])
                            ->where('status_type', 'reviewer')
                            ->latest()
                            ->first())->id,
                        'created_by' => $request->created_by,
                        'created_date' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            if (is_array($request->statistican) && ! empty($request->statistican)) {
                foreach ($request->statistican as $user) {
                    $statistican = $user['statistican'] ?? null;
                    $statisticanid = $user['statisticanid'] ?? null;
                    $existingAssignment = ProjectAssignDetails::where('project_id', $details->id)
                        ->where('id', $statisticanid)
                        ->where('type', 'statistican')
                        ->first();

                    if ($existingAssignment && $existingAssignment->assign_user !== $statistican) {
                        $existingEmployeeFreelancer = EmployeePaymentDetails::where('project_id', $details->id)
                            ->where('employee_id', $existingAssignment->assign_user)
                            ->where('type', 'statistican')
                            ->first();

                        if ($existingEmployeeFreelancer) {
                            $existingEmployeeFreelancer->employee_id = $statistican;
                            $existingEmployeeFreelancer->type = 'statistican';
                            $existingEmployeeFreelancer->save();
                        }
                    }
                    $existingAssignment1 = ProjectAssignDetails::where('project_id', $details->id)
                        ->where('status', 'correction')
                        ->where('type', 'team_coordinator')
                        // ->where('assign_user', $statistican)
                        ->where('type_sme', 'statistican')
                        ->first();

                    if ($existingAssignment1) {
                        $existingAssignment1->assign_user = $statistican;
                        $existingAssignment1->assign_date = $user['statisticanAssignedDate'];
                        $existingAssignment1->status = $user['statisticanStatus'];
                        $existingAssignment1->status_date = $user['statisticanStatusDate'];
                        $existingAssignment1->project_duration = $user['statisticanProjectDuration'];
                        $existingAssignment1->comments = $user['statistican_comment'] ?? '';
                        $existingAssignment1->save();
                    }

                    if ($existingAssignment) {
                        $existingAssignment->assign_user = $statistican;
                        $existingAssignment->assign_date = $user['statisticanAssignedDate'];
                        $existingAssignment->status = $user['statisticanStatus'];
                        $existingAssignment->status_date = $user['statisticanStatusDate'];
                        $existingAssignment->project_duration = $user['statisticanProjectDuration'];
                        $existingAssignment->comments = $user['statistican_comment'] ?? '';
                        $existingAssignment->save();

                        $userDetails = User::where('id', $statistican)->first();
                        if ($userDetails) {
                            try {
                                Mail::to($userDetails->email_address)->send(new AssignmentNotificationMail([
                                    'name' => $userDetails->employee_name,
                                    'role' => 'statistican',
                                    'project_id' => $customId,
                                    // 'title' => $details->title,
                                    // 'duration' => $details->projectduration,
                                    // 'unit' => $details->$durationUnit ?? null, // prevent undefined property error
                                ]));

                                Log::info("Email sent to statistican ({$userDetails->email_address}).");
                            } catch (\Exception $e) {
                                Log::error('Failed to send email to statistican: '.$e->getMessage());
                            }
                        } else {
                            Log::warning("User not found for statistican ID: {$user['statistican']}");
                        }
                    } else {
                        // Insert new record
                        $assigned_d = new ProjectAssignDetails;
                        $assigned_d->project_id = $details->id;
                        $assigned_d->assign_user = $user['statistican'];
                        $assigned_d->assign_date = $user['statisticanAssignedDate'];
                        $assigned_d->status = $user['statisticanStatus'];
                        $assigned_d->status_date = $user['statisticanStatusDate'];
                        $assigned_d->project_duration = $user['statisticanProjectDuration'];
                        $assigned_d->comments = $user['statistican_comment'];
                        $assigned_d->type = 'statistican';
                        $assigned_d->created_by = $request->created_by;
                        $assigned_d->save();
                    }

                    $userDetails = User::find($user['statistican']);

                    // $activity = new ProjectActivity;
                    // $activity->project_id = $details->id;
                    // $activity->activity = 'Project assigned to ' . $userDetails->employee_name;
                    // $activity->created_by = $request->created_by;
                    // $activity->created_date = now();
                    // $activity->save();

                    $existingAssignment = ProjectStatus::where('project_id', $details->id)
                        ->where('assign_id', $user['statistican'])
                        ->first();

                    if ($existingAssignment) {
                        Log::info('check data');
                    } else {
                        ProjectStatus::create([
                            'project_id' => $details->id,
                            'assign_id' => $user['statistican'],
                        ]);
                    }

                    $existingWriterLog = ProjectLogs::where('project_id', $details->id)
                        ->where('employee_id', $user['statistican'])
                        ->where('status_type', 'statistican')
                        ->latest()
                        ->first();

                    // if (!$existingWriterLog) {
                    ProjectLogs::create([
                        'project_id' => $details->id,
                        'employee_id' => $user['statistican'],
                        'assigned_date' => $user['statisticanAssignedDate'],
                        'status' => $user['statisticanStatus'],
                        'status_date' => $user['statisticanStatusDate'],
                        'status_type' => 'statistican',
                        'assing_preview_id' => optional(ProjectLogs::where('project_id', $details->id)
                            ->where('employee_id', $user['statistican'])
                            ->where('status_type', 'statistican')
                            ->latest()
                            ->first())->id,
                        'created_by' => $request->created_by,
                        'created_date' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            if (is_array($request->journal) && ! empty($request->journal)) {
                foreach ($request->journal as $user) {
                    $journal = $user['journal'] ?? null;
                    $journalid = $user['journalid'] ?? null;

                    $existingAssignment = ProjectAssignDetails::where('project_id', $details->id)
                        ->where('id', $journalid)
                        ->where('type', 'publication_manager')
                        ->first();
                    if ($existingAssignment && $existingAssignment->assign_user !== $journal) {
                        $existingEmployeeFreelancer = EmployeePaymentDetails::where('project_id', $details->id)
                            ->where('employee_id', $existingAssignment->assign_user)
                            ->where('type', 'publication_manager')
                            ->first();

                        if ($existingEmployeeFreelancer) {
                            $existingEmployeeFreelancer->employee_id = $journal;
                            $existingEmployeeFreelancer->type = 'publication_manager';
                            $existingEmployeeFreelancer->save();
                        }
                    }

                    if ($existingAssignment) {

                        $existingAssignment->assign_user = $journal;
                        $existingAssignment->assign_date = $user['journalAssignedDate'];
                        $existingAssignment->status = $user['journalStatus'];
                        $existingAssignment->status_date = $user['journalStatusDate'];
                        $existingAssignment->project_duration = $user['journalProjectDuration'];
                        $existingAssignment->comments = $user['journal_comment'] ?? '';
                        $existingAssignment->type_of_article = $user['type_of_article'] ?? '';
                        $existingAssignment->review = $user['review'] ?? '';
                        $existingAssignment->save();
                    } else {
                        // Insert new record
                        $assigned_d = new ProjectAssignDetails;
                        $assigned_d->project_id = $details->id;
                        $assigned_d->assign_user = $user['journal'];
                        $assigned_d->assign_date = $user['journalAssignedDate'];
                        $assigned_d->status = $user['journalStatus'];
                        $assigned_d->status_date = $user['journalStatusDate'];
                        $assigned_d->project_duration = $user['journalProjectDuration'];
                        $assigned_d->comments = $user['journal_comment'] ?? '';
                        $assigned_d->type = 'publication_manager';
                        $assigned_d->type_of_article = $user['type_of_article'] ?? '';
                        $assigned_d->review = $user['review'] ?? '';
                        $assigned_d->created_by = $request->created_by;
                        $assigned_d->save();
                    }

                    $existingAssignment = ProjectStatus::where('project_id', $details->id)
                        ->where('assign_id', $user['journal'])
                        ->first();

                    if ($existingAssignment) {
                        Log::info('check data');
                    } else {
                        ProjectStatus::create([
                            'project_id' => $details->id,
                            'assign_id' => $user['journal'],
                        ]);
                    }

                    $existingWriterLog = ProjectLogs::where('project_id', $details->id)
                        ->where('employee_id', $user['journal'])
                        ->where('status_type', 'publication_manager')
                        ->latest()
                        ->first();

                    // if (!$existingWriterLog) {
                    ProjectLogs::create([
                        'project_id' => $details->id,
                        'employee_id' => $user['journal'],
                        'assigned_date' => $user['journalAssignedDate'],
                        'status' => $user['journalStatus'],
                        'status_date' => $user['journalStatusDate'],
                        'status_type' => 'publication_manager',
                        'assing_preview_id' => optional(ProjectLogs::where('project_id', $details->id)
                            ->where('employee_id', $user['journal'])
                            ->where('status_type', 'publication_manager')
                            ->latest()
                            ->first())->id,
                        'created_by' => $request->created_by,
                        'created_date' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            //latest update
            // if ($request->has('entryprocess_documents') && is_array($request->entryprocess_documents)) {

            //     $defaultSpecificOption = null;
            //     $entryprocessDocuments = [];

            //     foreach ($request->entryprocess_documents as $document) {
            //         $fileNames = [];

            //         // Initialize a new EntryDocument always
            //         $entryDocument = new EntryDocument();
            //         $entryDocument->entry_process_model_id = $details->id;
            //         $entryDocument->created_by = $request->created_by ?? '-';

            //         // Handle select_document (specificOption) if available
            //         if (isset($document['specificOption']) && is_array($document['specificOption']) && !empty($document['specificOption'])) {
            //             $defaultSpecificOption = $document['specificOption']; // set default
            //             $entryDocument->select_document = json_encode($document['specificOption'], JSON_UNESCAPED_UNICODE);
            //         } elseif ($defaultSpecificOption !== null) {
            //             $entryDocument->select_document = json_encode($defaultSpecificOption, JSON_UNESCAPED_UNICODE); // use default if available
            //         }

            //         $entryDocument->save();

            //         // Handle file uploads if present
            //         if (isset($document['file']) && is_array($document['file'])) {
            //             foreach ($document['file'] as $file) {
            //                 if (!empty($file)) {
            //                     $originalName = $file->getClientOriginalName();
            //                     $originalExtension = $file->getClientOriginalExtension();
            //                     $cleanedName = strtolower(preg_replace('/[^a-z0-9]+/i', '-', pathinfo($originalName, PATHINFO_FILENAME)));
            //                     $cleanedName = str_replace('_', '', $cleanedName);
            //                     $uniqueName = $cleanedName . '.' . $originalExtension;

            //                     $path = public_path('uploads');
            //                     if (!is_dir($path)) {
            //                         mkdir($path, 0775, true);
            //                     }

            //                     $file->move($path, $uniqueName);

            //                     // Save file record
            //                     $documentList = new EntryDocumentsList();
            //                     $documentList->document_id = $entryDocument->id;
            //                     $documentList->file = $uniqueName;
            //                     $documentList->original_name = $cleanedName . '.' . $originalExtension;
            //                     $documentList->save();

            //                     $fileNames[] = $uniqueName;
            //                 }
            //             }
            //         }

            //         // Response formatting
            //         $entryprocessDocuments[] = [
            //             'specificOption' => $entryDocument->select_document ? json_decode($entryDocument->select_document) : null,
            //             'file' => $fileNames
            //         ];
            //     }

            //     // Return a successful response
            //     return response()->json([
            //         'entryprocess_documents' => $entryprocessDocuments
            //     ], 200);
            // } else {
            //     return response()->json(['error' => 'Invalid input data'], 400);
            // }

            //new
            if ($request->has('entryprocess_documents') && is_array($request->entryprocess_documents)) {

                $defaultSpecificOption = null;
                $entryprocessDocuments = [];

                foreach ($request->entryprocess_documents as $document) {
                    $fileNames = [];

                    // Try to find an existing entry document
                    $entryDocument = EntryDocument::where('entry_process_model_id', $details->id)
                        ->when(isset($document['id']), function ($q) use ($document) {
                            $q->where('id', $document['id']); // if id is passed
                        })
                        ->first();

                    // If not found, create new
                    if (! $entryDocument) {
                        $entryDocument = new EntryDocument;
                        $entryDocument->entry_process_model_id = $details->id;
                        $entryDocument->created_by = $request->created_by ?? '-';
                    }

                    // Handle select_document (specificOption)
                    if (isset($document['specificOption']) && is_array($document['specificOption']) && ! empty($document['specificOption'])) {
                        $defaultSpecificOption = $document['specificOption'];
                        $entryDocument->select_document = json_encode($document['specificOption'], JSON_UNESCAPED_UNICODE);
                    } elseif ($defaultSpecificOption !== null) {
                        $entryDocument->select_document = json_encode($defaultSpecificOption, JSON_UNESCAPED_UNICODE);
                    }

                    $entryDocument->save();

                    // Handle file uploads (append new files)
                    if (isset($document['file']) && is_array($document['file'])) {
                        foreach ($document['file'] as $file) {
                            if (! empty($file)) {
                                $originalName = $file->getClientOriginalName();
                                $originalExtension = $file->getClientOriginalExtension();
                                $cleanedName = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($originalName, PATHINFO_FILENAME));
                                $cleanedName = str_replace('_', '', $cleanedName);
                                $uniqueName = $cleanedName.'.'.$originalExtension;

                                $path = public_path('uploads');
                                if (! is_dir($path)) {
                                    mkdir($path, 0775, true);
                                }

                                $file->move($path, $uniqueName);

                                // Save new file record
                                $documentList = new EntryDocumentsList;
                                $documentList->document_id = $entryDocument->id;
                                $documentList->file = $uniqueName;
                                $documentList->original_name = $cleanedName.'.'.$originalExtension;
                                $documentList->save();

                                $fileNames[] = $uniqueName;
                            }
                        }
                    }

                    // Response formatting
                    $entryprocessDocuments[] = [
                        'specificOption' => $entryDocument->select_document ? json_decode($entryDocument->select_document) : null,
                        'file' => $fileNames,
                    ];
                }

                return response()->json([
                    'entryprocess_documents' => $entryprocessDocuments,
                ], 200);
            } else {
                return response()->json(['error' => 'Invalid input data'], 400);
            }
        });
    }

    public function assignUserByTc(Request $request)
    {
        $project_id = $request->project_id;
        $assign_user = $request->assign_user;
        $type = $request->type;
        $createdby = $request->created_by;
        $projectduration = $request->project_duration;

        // Create a DateTime object for now
        $assignDate = new \DateTime;

        // Add days based on project_duration
        if (is_numeric($projectduration) && $projectduration > 0) {
            $assignDate->modify("+{$projectduration} days");
        }

        // Format dates
        $assign_date_formatted = $assignDate->format('Y-m-d');
        $current_date_formatted = (new \DateTime)->format('Y-m-d');

        try {
            // Save to ProjectAssignDetails
            $details = new ProjectAssignDetails;
            $details->project_id = $project_id;
            $details->assign_user = $assign_user;
            $details->status = 'to_do';
            $details->type = $type;
            $details->created_by = $createdby;
            $details->project_duration = $assign_date_formatted;
            $details->assign_date = $current_date_formatted;
            $details->status_date = $current_date_formatted;
            $details->save();

            $entry_process = EntryProcessModel::where('id', $project_id)->first();
            if ($entry_process && $entry_process->process_status === 'not_assigned') {
                $entry_process->process_status = 'in_progress';
                $entry_process->save();
            }

            // Save to ProjectLogs
            $projectLogs = new ProjectLogs;
            $projectLogs->project_id = $project_id;
            $projectLogs->employee_id = $assign_user;
            $projectLogs->status = 'to_do';
            $projectLogs->assigned_date = $current_date_formatted;
            $projectLogs->status_date = $current_date_formatted;
            $projectLogs->created_by = $createdby;
            $projectLogs->created_date = $current_date_formatted;
            $projectLogs->save();

            //project status
            $projectStatus = new ProjectStatus;
            $projectStatus->project_id = $project_id;
            $projectStatus->assign_id = $assign_user;
            $projectStatus->status = 'pending';
            $projectStatus->save();
            $userDetails = User::where('id', $assign_user)->first();
            // $created = User::with('createdByUser')->find($request->created_by);

            //                 $employee = $created?->employee_name ?? 'Mohamed Ali';
            $creator = $userDetails?->createdByUser?->name ?? 'Admin';
            $createdUserDetails = User::where('id', $createdby)->first();
            $createdCreator = $createdUserDetails?->createdByUser?->name ?? 'Team Coordinator';

            $activity = new ProjectActivity;
            $activity->project_id = $project_id;
            $activity->activity = 'Project assigned to '.$userDetails->employee_name.' '.$type.' by'.$createdCreator;
            $activity->role = $createdCreator;
            $activity->created_by = $createdby;
            $activity->created_date = now();
            $activity->save();
            try {
                Mail::to($userDetails->email_address)->send(new AssignmentNotificationMail([
                    'name' => $userDetails->employee_name,
                    'role' => $type,
                    'project_id' => $entry_process->project_id,
                    // 'title' => $request->title,
                    // 'duration' => $request->projectduration,
                    // 'unit'       => $details->$durationUnit ?? null, // prevent undefined property error
                ]));

                Log::info("Email sent to reviewer ({$userDetails->email_address}).");
            } catch (\Exception $e) {
                Log::error('Failed to send email to reviewer: '.$e->getMessage());
            }

            return response()->json(['message' => 'User assigned successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to assign user',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $details = EntryProcessModel::where('is_deleted', 0)->find($id);

        if (! $details) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        $details->is_deleted = 1;
        $details->status = 0;
        $details->save();

        return response()->json($details);
    }

    public function deleteProjectById(Request $request)
    {
        $projectId = $request->query('project_id');
        $assignUser = $request->query('assign_user');
        $id = $request->query('id');

        try {
            $deleteProject = ProjectAssignDetails::where('id', $id)->where('project_id', $projectId)->where('assign_user', $assignUser)->delete();
            $deleteProjectLog = ProjectLogs::where('project_id', $projectId)
                ->where('employee_id', $assignUser)
                ->delete();
            $deleteStatus = ProjectStatus::where('project_id', $projectId)->where('assign_id', $assignUser)->delete();
            $deleteFreelancer = EmployeePaymentDetails::where('project_id', $projectId)->where('employee_id', $assignUser)->delete();

            return response()->json(['message' => 'Project deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Failed to delete project'], 500);
        }
    }

    public function documentDelete(Request $request, string $id)
    {
        $projectId = $request->query('project_id');
        $createdBy = $request->query('createdby');

        // Find the payment detail by ID
        $paymentDetail = EntryDocumentsList::find($id);

        if (! $paymentDetail) {
            return response()->json(['message' => 'Entry process details not found'], 404);
        }
        // Update the is_deleted field to 1
        $paymentDetail->is_deleted = 1;
        $paymentDetail->save();

        $paymentlist = EntryDocument::where('id', $paymentDetail->document_id)->first();

        $activity = new ProjectActivity;
        $activity->project_id = $paymentlist->entry_process_model_id;
        $activity->activity = 'Project document deleted successfully';
        $activity->role = 'Admin';
        $activity->created_by = $createdBy;
        $activity->created_date = date('Y-m-d H:i:s');
        $activity->save();

        return response()->json(['message' => 'Entry process document deleted successfully']);
    }

    public function documentRenameDoc(Request $request, string $id)
    {
        $document = EntryDocumentsList::find($id);

        if (! $document) {
            return response()->json(['message' => 'Entry process document not found'], 404);
        }

        $newName = $request->name;
        $document->original_name = $newName;
        $document->save();

        return response()->json(['message' => 'Document renamed successfully']);
    }

    public function filterID(string $id)
    {
        $details = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('is_deleted', 0)->find($id);

        return response()->json($details);
    }

    public function fetchInstitutions()
    {

        $institutions = InstitutionModel::where('is_deleted', 0)
            ->where('status', 'active')
            ->get(['name', 'id']);

        return response()->json($institutions);
    }

    public function fetchProjectTitle()
    {
        $validProjectIds = PaymentStatusModel::pluck('project_id');

        $projectTitle = EntryProcessModel::whereNotIn('id', $validProjectIds)->where('is_deleted', 0)
            ->groupBy('id')
            ->selectRaw('id, MAX(title) as title, MAX(project_id) as project_id')
            ->get();

        return response()->json($projectTitle);
    }

    public function fetchProjectTitleE()
    {
        $projectTitle = EntryProcessModel::where('is_deleted', 0)
            ->groupBy('id')
            ->selectRaw('id, MAX(title) as title, MAX(project_id) as project_id')
            ->get();

        return response()->json($projectTitle);
    }

    public function fetchDepartments()
    {
        $departments = DepartmentModel::where('is_deleted', 0)
            ->where('status', 'active')
            ->get(['name', 'id']);

        return response()->json($departments);
    }

    public function fetchProfessions()
    {
        $professions = ProfessionModel::where('is_deleted', 0)
            ->where('status', 'active')
            ->get(['name', 'id']);

        return response()->json($professions);
    }

    public function indexProjectId()
    {
        $entries = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->with(['paymentStatusModel', 'pendingStatusModel'])->where('is_deleted', 0)->get();

        return response()->json($entries);
    }

    public function showProjectId($project_id)
    {
        $entry = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->with(['paymentStatusModel', 'pendingStatusModel'])->where('is_deleted', 0)->where('project_id', $project_id)->first();

        if (! $entry) {
            return response()->json(['message' => 'Entry process not found'], 404);
        }

        return response()->json($entry);
    }

    public function updateProjectId(Request $request, $project_id)
    {
        // Find the entry process by project_id
        $entry = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('project_id', $project_id)->where('is_deleted', 0)->first();

        // Check if the entry process exists
        if (! $entry) {
            return response()->json(['message' => 'Entry process not found'], 404);
        }
        // Helper to clean date fields
        $nullifyIfEmpty = fn ($value) => empty($value) ? null : $value;
        // Update entry process fields
        $entry->entry_date = $request->entry_date ?? null;
        $entry->title = $request->title ?? null;
        $entry->type_of_work = $request->type_of_work ?? null;
        $entry->others = $request->others ?? null;
        $entry->select_document = $request->select_document ?? null;
        $entry->file = $request->file ?? null;
        $entry->client_name = $request->client_name ?? null;
        $entry->email = $request->email ?? null;
        $entry->contact_number = $request->contact_number ?? null;
        $entry->institute = $request->institute ?? null;
        $entry->department = $request->department ?? null;
        $entry->profession = $request->profession ?? null;
        $entry->budget = $request->budget ?? null;
        $entry->hierarchy_level = $request->hierarchy_level ?? null;
        $entry->comment_box = $request->comment_box ?? null;
        $entry->writer = $request->writer ?? null;
        $entry->writer_assigned_date = $request->writer_assigned_date ?? null;
        $entry->writer_status = $request->writer_status ?? null;
        $entry->writer_status_date = $request->writer_status_date ?? null;
        $entry->reviewer = $request->reviewer ?? null;
        $entry->reviewer_assigned_date = $request->reviewer_assigned_date ?? null;
        $entry->reviewer_status = $request->reviewer_status ?? null;
        $entry->reviewer_status_date = $request->reviewer_status_date ?? null;
        $entry->statistican = $request->statistican ?? null;
        $entry->statistican_assigned_date = $request->statistican_assigned_date ?? null;
        $entry->statistican_status = $request->statistican_status ?? null;
        $entry->statistican_status_date = $request->statistican_status_date ?? null;
        $entry->status = $request->status ?? 1;
        $entry->is_deleted = $request->is_deleted ?? 0;
        $entry->created_by = $request->created_by ?? 'Aryu';
        $entry->save();

        // Update payment process
        $paymentProcess = PaymentStatusModel::where('project_id', $project_id)->first();

        if ($paymentProcess) {
            // Ensure process_title is not null
            $paymentProcess->process_title = $request->process_title ?? 'Default Process Title';
            $paymentProcess->budget = $request->budget ?? ' ';
            $paymentProcess->payment_one = $request->payment_one ?? ' ';
            $paymentProcess->payment_one_date = $nullifyIfEmpty($request->payment_one_date);
            $paymentProcess->payment_two = $request->payment_two ?? '';
            $paymentProcess->payment_two_date = $nullifyIfEmpty($request->payment_two_date);
            $paymentProcess->payment_three = $request->payment_three ?? '';
            $paymentProcess->payment_three_date = $nullifyIfEmpty($request->payment_three_date);
            $paymentProcess->writer_payment = $request->writer_payment ?? '';
            $paymentProcess->writer_payment_date = $nullifyIfEmpty($request->writer_payment_date);
            $paymentProcess->reviewer_payment = $request->reviewer_payment ?? '';
            $paymentProcess->reviewer_payment_date = $nullifyIfEmpty($request->reviewer_payment_date);
            $paymentProcess->statistican_payment = $request->statistican_payment ?? '';
            $paymentProcess->statistican_payment_date = $nullifyIfEmpty($request->statistican_payment_date);
            $paymentProcess->journal_payment = $request->journal_payment ?? '';
            $paymentProcess->journal_payment_date = $nullifyIfEmpty($request->journal_payment_date);
            $paymentProcess->payment_status = $request->payment_status ?? '';
            $paymentProcess->status = $request->status ?? 1;
            $paymentProcess->is_deleted = $request->is_deleted ?? 0;
            $paymentProcess->save();
        }

        // Update pending process
        $pendingProcess = PendingStatusModel::where('project_id', $project_id)->first();

        if ($pendingProcess) {
            $pendingProcess->writer_pending_days = $request->writer_pending_days ?? null;
            $pendingProcess->reviewer_pending_days = $request->reviewer_pending_days ?? null;
            $pendingProcess->project_pending_days = $request->project_pending_days ?? null;
            $pendingProcess->writer_payment_due_date = $request->writer_payment_due_date ?? null;
            $pendingProcess->reviewer_payment_due_date = $request->reviewer_payment_due_date ?? null;
            $pendingProcess->status = $request->status ?? 1;
            $pendingProcess->save();
        }

        return response()->json([
            'entry_process' => $entry,
            'payment_process' => $paymentProcess ?? 'No payment process found',
            'pending_process' => $pendingProcess ?? 'No pending process found',
        ]);
    }

    //getting the value of type_of_work and fetch the value from project_id for each type_of_work
    public function fetchProjectId(Request $request)
    {
        $query = EntryProcessModel::where('is_deleted', 0)
            ->with(['paymentStatusModel', 'pendingStatusModel']);

        $totalCount = EntryProcessModel::where('is_deleted', 0)->count();

        $validColumns = [
            'entry_date',
            'title',
            'project_id',
            'type_of_work',
            'others',
            'select_document',
            'file',
            'client_name',
            'email',
            'contact_number',
            'institute',
            'department',
            'profession',
            'budget',
            'hierarchy_level',
            'comment_box',
            'writer',
            'writer_assigned_date',
            'writer_status',
            'writer_status_date',
            'writer_project_duration',
            'reviewer',
            'reviewer_assigned_date',
            'reviewer_status',
            'reviewer_status_date',
            'reviewer_project_duration',
            'statistican',
            'statistican_assigned_date',
            'statistican_status',
            'statistican_status_date',
            'statistican_project_duration',
            'created_by',
        ];

        $position = $request->get('position');
        $typeOfWork = $request->get('type_of_work');
        $createdBy = $request->get('created_by');

        $countQuery = clone $query;

        if ($position && in_array($position, $validColumns)) {
            $query->whereNotNull($position);
            $countQuery->whereNotNull($position);
        }

        // Dynamically filter by 'type_of_work' if provided
        if ($typeOfWork) {
            $query->where('type_of_work', $typeOfWork);
            $countQuery->where('type_of_work', $typeOfWork);
        }

        // Dynamically filter by 'created_by' if provided
        if ($createdBy) {
            $query->where('created_by', $createdBy);
            $countQuery->where('created_by', $createdBy);
        }

        // Retrieve the filtered data
        $data = $query->get($validColumns);

        // Count the filtered results using the cloned query
        $filteredCount = $countQuery->count();

        // Prepare and return the response
        return response()->json([
            // 'data' => $data,
            'filtered_count' => $filteredCount,
            'total_count' => $totalCount,
            'position' => $position,
            'type_of_work' => $typeOfWork,
        ]);
    }

    // public function dashboardProjectList(Request $request)
    // {
    //     $position = $request->get('position');

    //     $monthwiseData = $this->monthWiseTable($position);

    //     $currentYear = date('Y');

    //     $entries = EntryProcessModel::select('id', 'type_of_work', 'project_id', 'process_status', 'hierarchy_level', 'projectduration', 'created_by')->where('is_deleted', 0)->whereYear('entry_date', $currentYear)->get();

    //     // $projectStatusList = EntryProcessModel::with(['projectStatus' => function ($query) {
    //     //     $query->where(function ($q) {
    //     //         $q->where('status', 'rejected');
    //     //     });
    //     // }])
    //     //     ->where('is_deleted', 0)
    //     //     ->where('process_status', '!=', 'completed')
    //     //     ->whereYear('entry_date', $currentYear)
    //     //     ->get();
    //     $projectStatusList = EntryProcessModel::with('projectStatus') // Just eager-load the relation
    //         ->whereHas('projectStatus', function ($query) {
    //             $query->where('status', 'rejected')
    //                 ->orderBy('created_at', 'desc');
    //         })
    //         ->where('is_deleted', 0)
    //         ->where('process_status', '!=', 'completed')
    //         ->whereYear('entry_date', $currentYear)
    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     $projectStatusCount = $projectStatusList->count();

    //     // Initialize counters
    //     $typeOfWorkCounts = [
    //         'manuscript' => 0,
    //         'thesis' => 0,
    //         'statistics' => 0,
    //         'presentation' => 0,
    //         'others' => 0,
    //     ];
    //     $processStatusCounts = [
    //         'not_assigned' => 0,
    //         'pending_author' => 0,
    //         'withdrawal' => 0,
    //         'in_progress' => 0,
    //         'completed' => 0,
    //     ];
    //     $journalStatusCounts = [
    //         'submit_to_journal' => 0,
    //         'submitted' => 0,
    //         'peer_review' => 0,
    //         'reviewer_comments' => 0,
    //         'resubmission' => 0,
    //         'rejected' => 0,
    //     ];
    //     $completedCounts = $typeOfWorkCounts;
    //     $urgentImportantCount = 0;
    //     $notAssignedCount = 0;
    //     $projectDelayCount = 0;
    //     $freelancerPaymentCount = 0;
    //     $writerProjectCount = 0;
    //     $reviewerProjectCount = 0;

    //     $writerStatusCounts = [
    //         'completed' => 0,
    //         'on_going' => 0,
    //         'correction' => 0,
    //         'plag_correction' => 0,
    //     ];

    //     $reviewerStatusCounts = [
    //         'completed' => 0,
    //         'on_going' => 0,
    //         'correction' => 0,
    //         'plag_correction' => 0,
    //     ];

    //     $paymentStatusCounts = [
    //         'advance_pending' => 0,
    //         'partial_payment_pending' => 0,
    //         'final_payment_pending' => 0,
    //         'completed' => 0,
    //     ];

    //     $paymentEntries = PaymentStatusModel::select('payment_status', 'id')
    //         ->with(['projectData'])
    //         ->whereIn('payment_status', [
    //             'advance_pending',
    //             'partial_payment_pending',
    //             'final_payment_pending',
    //             'completed',
    //         ])
    //         ->whereHas('projectData', function ($query) {
    //             $query->where('is_deleted', 0);
    //         })

    //         ->get();

    //     $peopleIds_pm = People::where('position', '28')
    //         ->pluck('id')
    //         ->filter()
    //         ->values()
    //         ->toArray();

    //     $journalEntries = ProjectAssignDetails::select('status', 'id', 'project_id')
    //         ->where('type', 'publication_manager')
    //         ->whereIn('created_by', $peopleIds_pm)
    //         ->whereIn('status', array_keys($journalStatusCounts))
    //         ->get();

    //     $journalStatusIds = [
    //         'submit_to_journal' => [],
    //         'reviewer_comments' => [],
    //         'submitted' => [],
    //         'peer_review' => [],
    //         'resubmission' => [],
    //         'rejected' => [],
    //     ];

    //     foreach ($journalEntries as $entry) {
    //         if (isset($journalStatusCounts[$entry->status])) {
    //             $journalStatusCounts[$entry->status]++;

    //             $journalStatusIds[$entry->status][] = $entry->project_id;
    //         }
    //     }

    //     $submitted_peer_review_count = $journalStatusCounts['submitted'] + $journalStatusCounts['peer_review'];
    //     $submitted_peer_review_ids = array_merge($journalStatusIds['submitted'], $journalStatusIds['peer_review']);

    //     $resubmission_rejected_count = $journalStatusCounts['resubmission'] + $journalStatusCounts['rejected'];
    //     $resubmission_rejected_ids = array_merge($journalStatusIds['resubmission'], $journalStatusIds['rejected']);

    //     $result = [
    //         'submit_to_journal' => [
    //             'count' => $journalStatusCounts['submit_to_journal'],
    //             'ids' => $journalStatusIds['submit_to_journal'],
    //         ],
    //         'reviewer_comments' => [
    //             'count' => $journalStatusCounts['reviewer_comments'],
    //             'ids' => $journalStatusIds['reviewer_comments'],
    //         ],
    //         'submitted_peer_review' => [
    //             'count' => $submitted_peer_review_count,
    //             'ids' => $submitted_peer_review_ids,
    //         ],
    //         'resubmission_rejected' => [
    //             'count' => $resubmission_rejected_count,
    //             'ids' => $resubmission_rejected_ids,
    //         ],
    //     ];

    //     $typeOfWorkCounts = $typeOfWorkCounts ?? [];
    //     $processStatusCounts = $processStatusCounts ?? [];
    //     $journalStatusCounts = $journalStatusCounts ?? [];
    //     $completedCounts = $completedCounts ?? [];
    //     $urgentImportantCount = 0;
    //     $importantNotUrgentCount = 0;
    //     $urgentNotImportantCount = 0;
    //     $notUrgentNotImportantCount = 0;
    //     $notAssignedCount = 0;
    //     $projectDelayCount = 0;
    //     $writerCount = 0;
    //     $reviewerCount = 0;
    //     $writerStatusCounts = $writerStatusCounts ?? [];
    //     $reviewerStatusCounts = $reviewerStatusCounts ?? [];
    //     $freelancerPaymentCount = 0;
    //     $freelancers = [];
    //     $paymentStatusCounts = $paymentStatusCounts ?? [];

    //     $idsToCheck = [];
    //     $currentDate = now()->format('Y-m-d');
    //     $assignprojectIds = [];

    //     $projectIds = $entries->pluck('id')->toArray();
    //     // Count writer and reviewer
    //     $writerProjectCount = ProjectAssignDetails::whereIn('project_id', $projectIds)->where('type', 'writer')->count();

    //     $reviewerProjectCount = ProjectAssignDetails::whereIn('project_id', $projectIds)->where('type', 'reviewer')->count();

    //     $submitted_peer = ProjectAssignDetails::whereIn('status', ['submit_to_journal', 'peer_review'])->count();
    //     $resubmission_rejected = ProjectAssignDetails::whereIn('status', ['resubmission', 'rejected'])->count();

    //     $journalEntriesCount = ProjectAssignDetails::whereIn('status', [
    //         'submit_to_journal',
    //         'peer_review',
    //         'reviewer_comments',
    //     ])->count();

    //     foreach ($entries as $entry) {
    //         // Count type_of_work
    //         $typeOfWorkCounts[$entry->type_of_work] = ($typeOfWorkCounts[$entry->type_of_work] ?? 0) + 1;

    //         // Count process_status
    //         $processStatusCounts[$entry->process_status] = ($processStatusCounts[$entry->process_status] ?? 0) + 1;

    //         // if (isset($entry->journal_status)) {
    //         //     if ($entry->journal_status === 'submitted' || $entry->journal_status === 'peer_review') {
    //         //         $journalStatusCounts['submit_peer_review'] = ($journalStatusCounts['submit_peer_review'] ?? 0) + 1;
    //         //     } elseif ($entry->journal_status === 'resubmission' || $entry->journal_status === 'rejected') {
    //         //         $journalStatusCounts['resubmission_rejected'] = ($journalStatusCounts['resubmission_rejected'] ?? 0) + 1;
    //         //     } else {
    //         //         $journalStatusCounts[$entry->journal_status] = ($journalStatusCounts[$entry->journal_status] ?? 0) + 1;
    //         //     }
    //         // }

    //         // Count journal_status use $journalStatusCounts
    //         $journalStatusCounts[$entry->process_status] = ($journalStatusCounts[$entry->process_status] ?? 0) + 1;

    //         if ($entry->process_status === 'completed') {
    //             $completedCounts[$entry->type_of_work] = ($completedCounts[$entry->type_of_work] ?? 0) + 1;
    //         }

    //         if ($entry->hierarchy_level === 'urgent_important' && $entry->process_status !== 'completed') {
    //             $urgentImportantCount++;
    //         }

    //         if ($entry->hierarchy_level === 'important_not_urgent') {
    //             $importantNotUrgentCount++;
    //         }

    //         if ($entry->hierarchy_level === 'urgent_not_important') {
    //             $urgentNotImportantCount++;
    //         }

    //         if ($entry->hierarchy_level === 'not_urgent_not_important') {
    //             $notUrgentNotImportantCount++;
    //         }

    //         if ($entry->process_status === 'not_assigned' && $entry->process_status !== 'completed') {
    //             $notAssignedCount++;
    //         }
    //         $delayedProjects = [];

    //         $projectstatus = ProjectViewStatus::where('project_id', $entry->id)->Where('project_status', '!=', 'completed')->orderBy('id', 'desc')->latest()->first();

    //         $projectstatus_completeddate = $projectstatus ? $projectstatus->created_date : null;

    //         $projectDurationDate = $entry->projectduration;

    //         if ($projectstatus_completeddate) {

    //             if ($projectDurationDate < $currentDate) {
    //                 $projectDelayCount++;

    //                 $delayedProjects[] = [
    //                     'project_id' => $entry->project_id,
    //                     'id' => $entry->id,
    //                     'entry_date' => $entry->entry_date,
    //                     'hierarchy_level' => $entry->hierarchy_level,
    //                     'type_of_work' => $entry->type_of_work,
    //                     'title' => $entry->title,
    //                     'process_status' => $entry->process_status,
    //                     'writer' => $entry->writer,
    //                     'reviewer' => $entry->reviewer,
    //                     'statistican' => $entry->statistican,
    //                     'journal' => $entry->journal,
    //                     'writer_status' => $entry->writer_status,
    //                     'reviewer_status' => $entry->reviewer_status,
    //                     'statistican_status' => $entry->statistican_status,
    //                     'journal_status' => $entry->journal_status,
    //                     'client_name' => $entry->client_name,

    //                     'project_duration' => $entry->projectduration,
    //                 ];
    //             } else {
    //                 if ($projectDurationDate < $currentDate) {
    //                     $delayedProjects[] = [
    //                         'project_id' => $entry->project_id,
    //                         'id' => $entry->id,
    //                         'entry_date' => $entry->entry_date,
    //                         'hierarchy_level' => $entry->hierarchy_level,
    //                         'type_of_work' => $entry->type_of_work,
    //                         'title' => $entry->title,
    //                         'process_status' => $entry->process_status,
    //                         'writer' => $entry->writer,
    //                         'reviewer' => $entry->reviewer,
    //                         'statistican' => $entry->statistican,
    //                         'journal' => $entry->journal,
    //                         'writer_status' => $entry->writer_status,
    //                         'reviewer_status' => $entry->reviewer_status,
    //                         'statistican_status' => $entry->statistican_status,
    //                         'journal_status' => $entry->journal_status,
    //                         'client_name' => $entry->client_name,

    //                         'project_duration' => $entry->projectduration,
    //                     ];
    //                 }
    //             }
    //         }
    //         if ($entry->type_of_work === 'manuscript') {
    //             $assignProject = ProjectAssignDetails::select('status', 'type')->where('project_id', $entry->id)->get();

    //             foreach ($assignProject as $project) {
    //                 if ($project->type === 'writer') {
    //                     $writerStatusCounts[$project->status] = ($writerStatusCounts[$project->status] ?? 0) + 1;
    //                 }

    //                 if ($project->type === 'reviewer') {
    //                     $reviewerStatusCounts[$project->status] = ($reviewerStatusCounts[$project->status] ?? 0) + 1;
    //                 }
    //             }
    //         }

    //         //freeelancer count
    //         $assignproject = ProjectAssignDetails::where('project_id', $entry->id)->pluck('assign_user')->toArray();

    //         if (! empty($assignproject)) {
    //             $assignprojectIds[$entry->id] = array_unique($assignproject);
    //         }
    //     }

    //     $allAssignUserIds = array_unique(array_merge(...array_values($assignprojectIds)));

    //     if (! empty($allAssignUserIds)) {

    //         $userhrms = DB::connection('mysql_medics_hrms')
    //             ->table('employee_details')
    //             ->where('employee_type', 'freelancers')
    //             // ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
    //             ->whereIn('id', $allAssignUserIds)
    //             ->where('status', '1')
    //             ->get();

    //         $freelancersById = $userhrms->keyBy('id');

    //         // Assign freelancers to their respective projects
    //         foreach ($entries as $entry) {
    //             if (! isset($assignprojectIds[$entry->id])) {
    //                 continue;
    //             }

    //             $addedProjectIds = []; // To track unique project IDs

    //             foreach ($assignprojectIds[$entry->id] as $freelancerId) {
    //                 if (isset($freelancersById[$freelancerId])) {
    //                     $user = $freelancersById[$freelancerId];
    //                     // Check if project_id already added
    //                     if (! in_array($entry->project_id, $addedProjectIds)) {
    //                         $freelancerPaymentCount++;
    //                         $freelancers[] = [
    //                             // 'id' => $user->id,
    //                             // 'name' => $user->employee_name,
    //                             // 'employee_type' => $user->employee_type,
    //                             // 'email' => $user->email_address,
    //                             'id' => $entry->id,
    //                             'project_id' => $entry->project_id,
    //                             // 'entry_date' => $entry->entry_date,
    //                             'hierarchy_level' => $entry->hierarchy_level,
    //                             'type_of_work' => $entry->type_of_work,
    //                         ];

    //                         $addedProjectIds[] = $entry->project_id;
    //                     }
    //                 }
    //             }
    //         }
    //     }

    //     foreach ($paymentEntries as $pentry) {
    //         if (isset($pentry->payment_status)) {
    //             if (! isset($paymentStatusCounts[$pentry->payment_status])) {
    //                 $paymentStatusCounts[$pentry->payment_status] = 0;
    //             }

    //             $paymentStatusCounts[$pentry->payment_status]++;
    //         }
    //     }

    //     // foreach ($journalEntreies as $jentry) {
    //     //     if (isset($jentry->status)) {
    //     //         if (!isset($journalStatusCounts[$jentry->status])) {
    //     //             $journalStatusCounts[$jentry->status] = 0;
    //     //         }

    //     //         $journalStatusCounts[$jentry->status]++;
    //     //     }
    //     // }

    //     $filteredCount = $entries->count();
    //     $totalCount = EntryProcessModel::select('id')->where('is_deleted', 0)->whereYear('entry_date', $currentYear)->count();

    //     //todo list
    //     function getEntryProcessData($statuses, $currentYear, $pid = null)
    //     {
    //         $query = EntryProcessModel::select('id', 'hierarchy_level', 'project_id', 'process_status', 'created_by')
    //             ->with([
    //                 'userData',
    //                 'writerData',
    //                 'reviewerData',
    //                 'statisticanData',
    //                 'journalData',
    //             ])
    //             ->whereIn('process_status', $statuses)
    //             ->whereYear('entry_date', $currentYear)
    //             ->where('is_deleted', 0)
    //             ->where('process_status', '!=', 'completed');

    //         // Apply the created_by filter if $pid is provided
    //         if ($pid !== null) {
    //             $query = $query->where('created_by', $pid);
    //         }

    //         return $query->orderBy('id', 'desc')->get()->map(function ($item) {
    //             $hasAnyRole =
    //                 ($item->writerData && $item->writerData->isNotEmpty()) ||
    //                 ($item->reviewerData && $item->reviewerData->isNotEmpty()) ||
    //                 ($item->statisticanData && $item->statisticanData->isNotEmpty()) ||
    //                 ($item->journalData && $item->journalData->isNotEmpty());

    //             return [
    //                 'id' => $item->id,
    //                 'project_id' => $item->project_id,
    //                 'hierarchy_level' => $item->hierarchy_level,
    //                 'process_status' => $item->process_status,
    //                 'created_by' => $item->created_by,
    //                 'has_role' => $hasAnyRole ? true : false,
    //             ];
    //         });
    //     }

    //     function getEntryProcessCount($statuses, $currentYear, $pid = null)
    //     {
    //         $query = EntryProcessModel::select('id', 'hierarchy_level', 'project_id', 'process_status')
    //             ->with(['userData'])

    //             ->whereIn('process_status', $statuses)
    //             ->whereYear('entry_date', $currentYear)
    //             ->where('process_status', '!=', 'completed')
    //             ->where('is_deleted', 0);

    //         if ($pid !== null) {
    //             $query->where('created_by', $pid);
    //         }

    //         // Return the count of matching rows
    //         return $query->count();
    //     }

    //     $peopleIds_sme = People::where('position', '14')
    //         ->pluck('id')
    //         ->filter()
    //         ->values()
    //         ->toArray();

    //     if ($position === 'project_manager') {
    //         $statusesInProgress = ['not_assigned'];
    //         $pid = 9; // Set the pid for Project Manager
    //         $inToDoList = getEntryProcessData($statusesInProgress, $currentYear, $pid); // pass pid for Project Manager
    //         $inToDoListCount = getEntryProcessCount($statusesInProgress, $currentYear, $pid);
    //     } elseif ($position === 'team_coordinator') {
    //         $statusesInProgress = ['not_assigned', 'in_progress'];
    //         // $pid = 14;
    //         $pid = $peopleIds_sme;
    //         $inToDoList = getEntryProcessData($statusesInProgress, $currentYear, $pid); // pass pid for Team Member
    //         $inToDoListCount = getEntryProcessCount($statusesInProgress, $currentYear, $pid);
    //     } elseif ($position === 'accountant') {
    //         $statusesInProgress = ['completed'];
    //         $pid = 9; // Set the pid for Project Manager
    //         $inToDoList = getEntryProcessData($statusesInProgress, $currentYear, $pid); // pass pid for Project Manager
    //         $inToDoListCount = getEntryProcessCount($statusesInProgress, $currentYear, $pid);
    //     } else {
    //         $statusesInProgress = ['not_assigned', 'in_progress'];
    //         // $pid = 14;
    //         $pid = $peopleIds_sme;
    //         $inToDoList = getEntryProcessData($statusesInProgress, $currentYear, $pid); // pass pid for Team Member
    //         $inToDoListCount = getEntryProcessCount($statusesInProgress, $currentYear, $pid);
    //     }

    //     // $tc_to_do = ProjectAssignDetails::with([
    //     //     'projectData.writerData',
    //     //     'projectData.reviewerData'
    //     // ])
    //     //     ->where('status', 'correction')
    //     //     ->where('type', 'team_coordinator')
    //     //     ->whereHas('projectData', function ($q) {
    //     //         $q->where("process_status", "!=", "completed");
    //     //         // Exclude if any writerData has active statuses
    //     //         $q->whereDoesntHave('writerData', function ($subQ) {
    //     //             $subQ->whereIn('status', [
    //     //                 'to_do',
    //     //                 'on_going',
    //     //                 'need_support',
    //     //                 'correction',
    //     //                 'plag_correction'
    //     //             ]);
    //     //         });
    //     //         // Exclude if any reviewerData has active statuses
    //     //         $q->whereDoesntHave('reviewerData', function ($subQ) {
    //     //             $subQ->whereIn('status', [
    //     //                 'to_do',
    //     //                 'on_going',
    //     //                 'need_support',
    //     //                 'correction',
    //     //                 'plag_correction'
    //     //             ]);
    //     //         });
    //     //         // Exclude if any smeData has 'need_support'
    //     //         $q->whereDoesntHave('smeData', function ($subQ) {
    //     //             $subQ->where('status', 'need_support');
    //     //         });
    //     //     })
    //     //     ->get()
    //     //     ->unique('project_id');

    //     // $tc_to_do = ProjectAssignDetails::with([
    //     //     'projectData.writerData',
    //     //     'projectData.reviewerData'
    //     // ])
    //     //     ->where('status', 'correction')
    //     //     ->where('type', 'team_coordinator')
    //     //     ->whereHas('projectData', function ($q) {
    //     //         // Exclude completed projects
    //     //         $q->where('process_status', '!=', 'completed');

    //     //         // Exclude if writer is still working (i.e., not completed)
    //     //         $q->whereDoesntHave('writerData', function ($subQ) {
    //     //             $subQ->whereIn('status', [
    //     //                 'to_do',
    //     //                 'on_going',
    //     //                 'correction',
    //     //                 'plag_correction'
    //     //             ]);
    //     //         });

    //     //         // Exclude if reviewer is still working
    //     //         $q->whereDoesntHave('reviewerData', function ($subQ) {
    //     //             $subQ->whereIn('status', [
    //     //                 'to_do',
    //     //                 'on_going',
    //     //                 'correction',
    //     //                 'plag_correction'
    //     //             ]);
    //     //         });

    //     //         // Allow if at least one of the following is completed or deleted:
    //     //         $q->where(function ($query) {
    //     //             $query->whereHas('writerData', function ($q1) {
    //     //                 $q1->where('status', 'completed');
    //     //             })
    //     //                 ->orWhereHas('reviewerData', function ($q2) {
    //     //                     $q2->where('status', 'completed');
    //     //                 })
    //     //                 ->orWhereHas('statisticanData', function ($q3) {
    //     //                     $q3->where('status', 'completed');
    //     //                 })
    //     //                 ->orWhereHas('tcData', function ($q4) {
    //     //                     $q4->where('is_deleted', '1');
    //     //                 });
    //     //         });

    //     //         // Exclude if SME needs support
    //     //         $q->whereDoesntHave('smeData', function ($subQ) {
    //     //             $subQ->where('status', 'need_support');
    //     //         });
    //     //     })
    //     //     ->get()
    //     //     ->unique('project_id');
    //     $tc_to_do = ProjectAssignDetails::with([
    //         'projectData.writerData',
    //         'projectData.reviewerData',
    //         'projectData.statisticanData',
    //         'projectData.tcData',
    //     ])
    //         ->where('status', 'correction')
    //         ->where('type', 'team_coordinator')
    //         ->orderBy('updated_at', 'desc')
    //         ->whereHas('projectData', function ($q) {
    //             $q->where('process_status', '!=', 'completed')
    //                 ->where('is_deleted', 0);
    //             // $q->whereDoesntHave('writerData', function ($subQ) {
    //             //     $subQ->whereIn('status', [
    //             //         'to_do',
    //             //         'on_going',
    //             //         'correction',
    //             //         'plag_correction',
    //             //         'rejected',
    //             //         'revert'
    //             //     ]);
    //             // });
    //             $q->where(function ($innerQ) {
    //                 $innerQ->where(function ($subInnerQ) {
    //                     // Non-thesis → block to_do & on_going also
    //                     $subInnerQ->where('type_of_work', '!=', 'thesis')
    //                         ->whereDoesntHave('writerData', function ($subQ) {
    //                             $subQ->whereIn('status', [
    //                                 'to_do',
    //                                 'on_going',
    //                                 'correction',
    //                                 'plag_correction',
    //                                 'rejected',
    //                                 'revert',
    //                                 'need_support',
    //                             ]);
    //                         });
    //                 })
    //                     ->orWhere(function ($subInnerQ) {
    //                         // Thesis → only block correction, plag_correction, rejected, revert
    //                         $subInnerQ->where('type_of_work', 'thesis')
    //                             ->whereDoesntHave('writerData', function ($subQ) {
    //                                 $subQ->whereIn('status', [
    //                                     'correction',
    //                                     'plag_correction',
    //                                     'rejected',
    //                                     'revert',
    //                                     'need_support',
    //                                 ]);
    //                             });
    //                     });
    //             });
    //             // $q->where(function ($query) {
    //             //     $query->whereDoesntHave('writerData', function ($subQ) {
    //             //         $subQ->whereIn('status', [
    //             //             'to_do',
    //             //             'on_going',
    //             //             'correction',
    //             //             'plag_correction',
    //             //             'rejected',
    //             //             'revert'
    //             //         ]);
    //             //     })
    //             //         ->where(function ($subQ) {
    //             //             $subQ->whereDoesntHave('writerData', function ($wq) {
    //             //                 // ensure no incomplete writerData
    //             //                 $wq->where('status', '!=', 'completed');
    //             //             })
    //             //                 ->whereDoesntHave('tcData', function ($tcQ) {
    //             //                     $tcQ->where('status', 'correction')
    //             //                         ->where('type', 'team_coordinator')
    //             //                         ->where('type_sme', 'statistican');
    //             //                 });
    //             //         });
    //             // });

    //             // $q->whereDoesntHave('reviewerData', function ($subQ) {
    //             //     $subQ->whereIn('status', [
    //             //         'to_do',
    //             //         'on_going',
    //             //         'correction',
    //             //         'plag_correction',
    //             //         'rejected',
    //             //         'revert'
    //             //     ]);
    //             // });

    //             // Reviewer condition → skip to_do/on_going for thesis
    //             $q->where(function ($innerQ) {
    //                 $innerQ->where(function ($subInnerQ) {
    //                     // Non-thesis → block to_do & on_going also
    //                     $subInnerQ->where('type_of_work', '!=', 'thesis')
    //                         ->whereDoesntHave('reviewerData', function ($subQ) {
    //                             $subQ->whereIn('status', [
    //                                 'to_do',
    //                                 'on_going',
    //                                 'correction',
    //                                 'plag_correction',
    //                                 'rejected',
    //                                 'revert',
    //                                 'need_support',
    //                             ]);
    //                         });
    //                 })
    //                     ->orWhere(function ($subInnerQ) {
    //                         $subInnerQ->where('type_of_work', 'thesis')
    //                             ->whereDoesntHave('reviewerData', function ($subQ) {
    //                                 $subQ->whereIn('status', [
    //                                     'correction',
    //                                     'plag_correction',
    //                                     'rejected',
    //                                     'revert',
    //                                     'need_support',
    //                                 ]);
    //                             });
    //                     });
    //             });
    //             // $q->whereDoesntHave('tcData', function ($subQ) {
    //             //     $subQ->where('status', 'correction')
    //             //         ->where('type', 'team_coordinator')
    //             //         ->where('type_sme', 'statistican');
    //             // });
    //             $q->whereDoesntHave('statisticanData', function ($subQ) {
    //                 $subQ->whereIn('status', [
    //                     'to_do',
    //                     'on_going',
    //                     'correction',
    //                     'plag_correction',
    //                     'rejected',
    //                     'revert',
    //                     'need_support',
    //                 ]);
    //             });

    //             $q->whereDoesntHave('projectAcceptStatust', function ($sq) {
    //                 $sq->where('status', 'rejected');
    //             });

    //             $q->whereDoesntHave('smeData', function ($subQ) {
    //                 $subQ->where('status', 'need_support');
    //             });
    //         })
    //         ->get()
    //         ->unique('project_id')
    //         ->filter(function ($row) {
    //             if ($row->projectData->tcData->isNotEmpty()) {
    //                 return true;
    //             }

    //             $writerStatus = optional($row->projectData->writerData->first())->status;
    //             $reviewerStatus = optional($row->projectData->reviewerData->first())->status;
    //             $statisticianStatus = optional($row->projectData->statisticanData->first())->status;

    //             return ! (
    //                 $writerStatus === 'completed' &&
    //                 $reviewerStatus === 'completed' &&
    //                 $statisticianStatus === 'completed'
    //             );
    //         })
    //         ->values();

    //     $peopleIds_sme = People::where('position', '13')
    //         ->pluck('id')
    //         ->filter()
    //         ->values()
    //         ->toArray();

    //     $projectAssignDetails = ProjectAssignDetails::pluck('project_id');

    //     $tcTodoListQuery = EntryProcessModel::with([
    //         'userData',
    //         'writerData',
    //         'reviewerData',
    //         'statisticanData',
    //         'journalData',
    //     ])

    //         ->where('process_status', 'in_progress')
    //         ->whereYear('entry_date', $currentYear)
    //         ->where('is_deleted', 0)
    //         ->where('process_status', '!=', 'completed')
    //         ->whereIn('created_by', $peopleIds_sme)
    //         ->limit(100);

    //     if ($projectAssignDetails->isNotEmpty()) {
    //         $tcTodoListQuery->whereNotIn('id', $projectAssignDetails);
    //     }

    //     $tcTodoList = $tcTodoListQuery->orderBy('id', 'desc')->get();

    //     $adminTodoListQuery = EntryProcessModel::with([
    //         'userData',
    //         'writerData',
    //         'reviewerData',
    //         'statisticanData',
    //         'journalData',
    //     ])
    //         ->where('process_status', 'in_progress')
    //         ->whereYear('entry_date', $currentYear)
    //         ->where('is_deleted', 0)
    //         ->where('process_status', '!=', 'completed')
    //         ->where('created_by', 9);

    //     if ($projectAssignDetails->isNotEmpty()) {
    //         $adminTodoListQuery->whereNotIn('id', $projectAssignDetails);
    //     }

    //     $adminTodoList = $adminTodoListQuery->orderBy('id', 'desc')->get();

    //     $todoItems = collect($tcTodoList)
    //         ->merge(
    //             collect($adminTodoList)
    //                 ->filter(function ($item) {
    //                     return ! empty($item->writerData)
    //                         || ! empty($item->reviewerData)
    //                         || ! empty($item->statisticanData)
    //                         || ! empty($item->journalData);
    //                 })
    //         )
    //         ->merge($tc_to_do)
    //         ->sortByDesc('updated_at')
    //         ->map(function ($item) {
    //             $hasAnyRole =
    //                 (isset($item->writerData) && $item->writerData->isEmpty()) ||
    //                 (isset($item->reviewerData) && $item->reviewerData->isEmpty()) ||
    //                 (isset($item->statisticanData) && $item->statisticanData->isEmpty()) ||
    //                 (isset($item->journalData) && $item->journalData->isEmpty());

    //             return [
    //                 'id' => $item->id ?? null,
    //                 'project_id' => $item->project_id ?? null,
    //                 'project_ids' => $item->projectData->project_id ?? null,
    //                 'hierarchy_level' => $item->hierarchy_level ?? null,
    //                 'hierarchy_levels' => $item->projectData->hierarchy_level ?? null,
    //                 'process_status' => $item->process_status ?? null,
    //                 'process_statuses' => $item->projectData->process_status ?? null,
    //                 'created_by' => $item->created_by ?? null,
    //                 'has_role' => $hasAnyRole,
    //             ];
    //         })
    //         ->values();

    //     $TcNotAssigned = $todoItems->filter(function ($item) {
    //         return $item['has_role'] === true;
    //     })->pluck('id')->all();
    //     $hasRoleTrueCount = count($TcNotAssigned);

    //     $merged_to_do_list = $tcTodoList->merge($adminTodoList)->merge($tc_to_do);
    //     $merged_to_do_list_count = $merged_to_do_list->count();

    //     //not assigned tc
    //     $projectIds = isset($projectIds) ? $projectIds : [];

    //     $revertdetails = ProjectAssignDetails::with([
    //         'projectData.writerData',
    //         'projectData.reviewerData',
    //         'projectData.statisticanData',
    //         'projectData.tcData',
    //     ])
    //         ->whereIn('project_id', $projectIds)
    //         ->where('status', 'revert')
    //         ->orderBy('updated_at', 'desc')
    //         ->whereHas('projectData', function ($query) {
    //             $query->where('is_deleted', 0)
    //                 ->where('process_status', '!=', 'completed')
    //                 ->whereDoesntHave('projectAcceptStatust', function ($sq) {
    //                     $sq->where('status', 'rejected');
    //                 })
    //                 ->whereDoesntHave('writerData', function ($sq) {
    //                     $sq->whereIn('status', ['to_do', 'on_going']);
    //                 })
    //                 ->whereDoesntHave('reviewerData', function ($sq) {
    //                     $sq->whereIn('status', ['to_do', 'on_going', 'correction']);
    //                 })
    //                 ->whereDoesntHave('statisticanData', function ($sq) {
    //                     $sq->whereIn('status', ['to_do', 'on_going']);
    //                 });
    //         })
    //         ->get()
    //         ->unique('project_id');

    //     $notAssignedProjects = EntryProcessModel::where('process_status', 'not_assigned')
    //         ->select('id', 'project_id', 'type_of_work', 'process_status', 'hierarchy_level', 'created_at')
    //         ->where('is_deleted', 0)
    //         ->orderBy('updated_at', 'desc')
    //         ->get();

    //     $statisticianWithoutWriter = ProjectAssignDetails::with([
    //         'projectData:id,project_id,type_of_work,process_status,hierarchy_level,created_at',
    //     ])
    //         ->where('type', 'team_coordinator')
    //         ->whereIn('type_sme', ['writer', 'Publication Manager', 'reviewer', '2nd_writer'])
    //         ->where('status', 'completed')
    //         ->whereNotIn('status', ['need_support'])
    //         ->whereHas('projectData', function ($query) {
    //             $query->where('is_deleted', 0)
    //                 ->where('process_status', '!=', 'completed')
    //                 ->whereDoesntHave('writerData', function ($sq) {
    //                     $sq->whereIn('status', ['correction', 'to_do', 'on_going']);
    //                 })
    //                 ->whereDoesntHave('reviewerData', function ($sq) {
    //                     $sq->whereIn('status', ['correction', 'to_do', 'need_support', 'revert', 'on_going']);
    //                 });

    //         })
    //         ->select('project_id', 'status', 'type', 'updated_at')
    //         ->orderBy('updated_at', 'desc')
    //         ->get()
    //         ->unique('project_id');

    //     $writerCompletedProjects = ProjectAssignDetails::whereIn('status', ['completed'])
    //         ->with(['projectData:id,project_id,type_of_work,process_status,hierarchy_level,created_at'])
    //         ->where('type', 'writer')
    //         ->whereIn('project_id', $projectIds)
    //         ->orderBy('updated_at', 'desc')
    //         ->whereHas('projectData', function ($query) {
    //             $query->where('is_deleted', 0)
    //                 ->where('process_status', '!=', 'completed')
    //                 ->whereDoesntHave('projectAcceptStatust', function ($sq) {
    //                     $sq->where('status', 'rejected');
    //                 })
    //                 ->whereDoesntHave('writerData', function ($sq) {
    //                     $sq->whereIn('status', ['to_do', 'on_going']);
    //                 })
    //                 ->whereDoesntHave('reviewerData', function ($sq) {
    //                     $sq->whereIn('status', ['to_do', 'on_going', 'correction']);
    //                 });
    //         })
    //         ->select('project_id', 'status', 'type', 'updated_at')
    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     $allWriterProjects = $writerCompletedProjects->unique('project_id')->values();

    //     $writerProjectIds = $allWriterProjects->pluck('project_id')->unique()->toArray();

    //     $reviewerProjects = ProjectAssignDetails::whereIn('project_id', $writerProjectIds)
    //         ->where('type', 'reviewer')
    //         ->pluck('project_id')
    //         ->unique()
    //         ->toArray();

    //     $writerWithoutReviewer = $allWriterProjects->filter(function ($writer) use ($reviewerProjects) {
    //         $typeOfWork = $writer->projectData->type_of_work ?? null;

    //         // Count completed writers
    //         $writerCount = ProjectAssignDetails::where('project_id', $writer->project_id)
    //             ->where('type', 'writer')
    //             ->where('status', 'completed')
    //             ->count();

    //         // if ($typeOfWork === 'thesis' && $writerCount === 2) {
    //         //     // Count reviewers assigned (any status)
    //         //     $reviewerCount = ProjectAssignDetails::where('project_id', $writer->project_id)
    //         //         ->where('type', 'reviewer')
    //         //         ->count();

    //         //     return $reviewerCount < 2;
    //         // }

    //         return ! in_array($writer->project_id, $reviewerProjects);
    //     })
    //         ->unique('project_id')
    //         ->sortByDesc('updated_at')
    //         ->values();

    //     // $revert_writer = $writerWithoutReviewer;
    //     $revert_writer = collect()
    //         // ->merge($writerWithoutReviewer)
    //         ->merge($statisticianWithoutWriter)
    //         ->sortByDesc('updated_at')
    //         ->unique('project_id')
    //         ->values();

    //     $tc_notAssigned = $revert_writer->count();

    //     // $revertCounts = $revertdetails->count();

    //     // In progress
    //     $statusesInProgress = ['in_progress'];
    //     $inProgress = getEntryProcessData($statusesInProgress, $currentYear);
    //     $inProgressCount = getEntryProcessCount($statusesInProgress, $currentYear);

    //     // To-do list
    //     $statusesToDo = ['to_do', 'not_assigned'];
    //     $tasks = getEntryProcessData($statusesToDo, $currentYear);
    //     $to_docount = getEntryProcessCount($statusesToDo, $currentYear);

    //     // In work list
    //     $inWorksStatuses = ['pending_author'];
    //     $inWorks = getEntryProcessData($inWorksStatuses, $currentYear);
    //     $inWorksCount = getEntryProcessCount($inWorksStatuses, $currentYear);

    //     // Review list
    //     $reviewsStatuses = ['withdrawal'];
    //     $reviews = getEntryProcessData($reviewsStatuses, $currentYear);
    //     $reviewerCount = getEntryProcessCount($reviewsStatuses, $currentYear);

    //     // Completed list
    //     $completedStatuses = ['completed'];
    //     $completed = getEntryProcessData($completedStatuses, $currentYear);
    //     $completedCount = getEntryProcessCount($completedStatuses, $currentYear);

    //     // Correction list
    //     $correctionsStatuses = ['in_progress'];
    //     $corrections = getEntryProcessData($correctionsStatuses, $currentYear);
    //     $correctionsCount = getEntryProcessCount($correctionsStatuses, $currentYear);

    //     //people wise response data
    //     $totalProjects = People::select('id', 'employee_name', 'position')->with(['createdByUser'])
    //         ->where('position', '!=', 'Admin')
    //         ->whereIn('position', [7, 8, 10, 11])
    //         ->get();

    //     // Initialize common queries for EntryProcessModel based on the position
    //     $entryProcessData = EntryProcessModel::with(['writerData', 'reviewerData', 'statisticanData'])
    //         ->select('id', 'writer', 'reviewer', 'journal', 'statistican')->whereIn('writer', $totalProjects->pluck('id'))
    //         ->whereHas('reviewerData', function ($query) use ($totalProjects) {
    //             $query->where('status', '!=', 'completed')
    //                 ->whereIn('assign_user', $totalProjects->pluck('id')->toArray());
    //         })
    //         ->whereHas('writerData', function ($query) use ($totalProjects) {
    //             $query->where('status', '!=', 'completed')
    //                 ->whereIn('assign_user', $totalProjects->pluck('id')->toArray());
    //         })
    //         ->whereHas('statisticanData', function ($query) use ($totalProjects) {
    //             $query->where('status', '!=', 'completed')
    //                 ->whereIn('assign_user', $totalProjects->pluck('id')->toArray());
    //         })
    //         // ->orWhereIn('statistican', $totalProjects->pluck('id'))
    //         ->whereYear('entry_date', $currentYear)
    //         ->where('process_status', '!=', 'completed')
    //         ->where('is_deleted', 0)
    //         ->get();

    //     $projectLogsData = ProjectLogs::select('id', 'project_id', 'employee_id', 'status', 'status_date', 'status_type')->whereIn('employee_id', $totalProjects->pluck('id'))
    //         ->whereHas('entryProcess', function ($query) {
    //             $query->where('is_deleted', 0)
    //                 ->where('process_status', '!=', 'completed');
    //         })
    //         ->where('status', 'to_do')
    //         ->get();

    //     // Loop through each person and count based on their position
    //     foreach ($totalProjects as $entry) {
    //         $emp_pos = $entry->position;
    //         $emp_id = $entry->id;

    //         // Initialize counts
    //         $writerCount = $reviewerCount = $journalCount = $statisticanCount = 0;
    //         $writerPendingCount = $reviewerPendingCount = $statisticanPendingCount = $journalPendingCount = 0;
    //         $completedIn4Days = $completedIn5To8Days = $completedInMoreThan8Days = 0;
    //         $projectlist = [];

    //         // Filter EntryProcessData by role
    //         $filteredEntries = $entryProcessData->filter(function ($item) use ($emp_id, $emp_pos) {
    //             switch ($emp_pos) {
    //                 case 7:
    //                     return $item->writerData->assign_user == $emp_id;
    //                 case 8:
    //                     return $item->reviewerData->assign_user == $emp_id;
    //                 case 10:
    //                     return $item->journalData->assign_user == $emp_id;
    //                 case 11:
    //                     return $item->statisticanData->assign_user == $emp_id;
    //                 default:
    //                     return false;
    //             }
    //         });

    //         // Calculate counts
    //         $entry->writer_count = $filteredEntries->filter(function ($item) use ($emp_id) {
    //             return $item->writerData && $item->writerData->assign_user == $emp_id && $item->process_status !== 'completed';
    //         })->count();
    //         $entry->reviewer_count = $filteredEntries->filter(function ($item) use ($emp_id) {
    //             return $item->reviewerData && $item->reviewerData->assign_user == $emp_id && $item->process_status !== 'completed';
    //         })->count();
    //         $entry->journal_count = $filteredEntries->filter(function ($item) use ($emp_id) {
    //             return $item->journalData && $item->journalData->assign_user == $emp_id && $item->process_status !== 'completed';
    //         })->count();
    //         $entry->statistican_count = $filteredEntries->filter(function ($item) use ($emp_id) {
    //             return $item->statisticanData && $item->statisticanData->assign_user == $emp_id && $item->process_status !== 'completed';
    //         })->count();

    //         // Pending counts
    //         $entry->writerPendingCount = $filteredEntries->filter(function ($item) use ($emp_id) {
    //             return $item->writerData && $item->writerData->assign_user == $emp_id && $item->process_status !== 'completed';
    //         })->count();
    //         $entry->reviewerPendingCount = $filteredEntries->filter(function ($item) use ($emp_id) {
    //             return $item->reviewerData && $item->reviewerData->assign_user == $emp_id && $item->process_status !== 'completed';
    //         })->count();
    //         $entry->journalPendingCount = $filteredEntries->filter(function ($item) use ($emp_id) {
    //             return $item->journalData && $item->journalData->assign_user == $emp_id && $item->process_status !== 'completed';
    //         })->count();
    //         $entry->statisticanPendingCount = $filteredEntries->filter(function ($item) use ($emp_id) {
    //             return $item->statisticanData && $item->statisticanData->assign_user == $emp_id && $item->process_status !== 'completed';
    //         })->count();

    //         // // Get project list for this employee
    //         $projectlist = $projectLogsData->where('employee_id', $emp_id);

    //         // Loop through the projects to calculate the date differences
    //         foreach ($projectlist as $project) {

    //             $statusDate = Carbon::parse($project->status_date);
    //             $daysDifference = $statusDate->diffInDays($statusDate);

    //             if ($daysDifference < 4) {
    //                 $completedIn4Days++;
    //             } elseif ($daysDifference >= 5 && $daysDifference <= 8) {
    //                 $completedIn5To8Days++;
    //             } elseif ($daysDifference > 8) {
    //                 $completedInMoreThan8Days++;
    //             }
    //         }

    //         // Add completed days counts to the entry
    //         $entry->completed_in_4_days = $completedIn4Days;
    //         $entry->completed_in_5_to_8_days = $completedIn5To8Days;
    //         $entry->completed_in_more_than_8_days = $completedInMoreThan8Days;
    //     }

    //     //inhouse projects
    //     $totalProjectsInhouse = People::select('id', 'position', 'employee_name', 'employee_type')
    //         ->where('position', '!=', 'Admin')
    //         ->where('employee_type', '!=', 'freelancers')
    //         ->whereIn('position', [7, 8, 11])
    //         ->get()
    //         ->map(function ($person) {
    //             $person->created_by_users = $person->created_by_users; // Access the accessor

    //             return $person;
    //         });

    //     foreach ($totalProjectsInhouse as $entry) {
    //         $emp_pos = $entry->position;
    //         $emp_id = $entry->id;
    //         $positions = explode(',', $emp_pos);

    //         // Initialize count variables
    //         $writerCount = $reviewerCount = $journalCount = $statisticanCount = 0;
    //         $writerPendingCount = $reviewerPendingCount = $journalPendingCount = $statisticanPendingCount = 0;
    //         $writerOngoingCount = $reviewerOngoingCount = $writerNeedCount = $reviewerNeedCount = $statisticanNeedCount = 0;
    //         $writerCorrectionCount = $reviewerCorrectionCount = $statisticanOngoingCount = $statisticanCorrectionCount = 0;

    //         $projectlist = [];

    //         $writerDataId = ProjectAssignDetails::where('assign_user', $emp_id)->get()->pluck('project_id')->toArray();
    //         // Process position-wise counts
    //         if (in_array('7', $positions)) {
    //             $writerAssignment = ProjectAssignDetails::with(['projectData', 'employee_rejected'])
    //                 ->where('assign_user', $emp_id)
    //                 ->where('type', 'writer')
    //                 ->get()
    //                 ->pluck('projectData.id')
    //                 ->filter();

    //             $entry->writer_project_ids = $writerAssignment;
    //             $writerCount = $writerAssignment->count();
    //             $writerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereHas('employee_rejected', function ($query) {
    //                     $query->where('status', '!=', 'rejected');
    //                 })
    //                 ->where('type', 'writer')
    //                 ->count();
    //             $writerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['on_going', 'to_do'])
    //                 ->where('type', 'writer')->count();
    //             $writerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->where('status', 'need_support')
    //                 ->where('type', 'writer')->count();
    //             $writerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['correction', 'plag_correction'])
    //                 ->where('type', 'writer')->count();
    //         }

    //         if (in_array('8', $positions)) {
    //             $reviewerAssignments = ProjectAssignDetails::with(['projectData', 'employee_rejected'])
    //                 ->where('assign_user', $emp_id)
    //                 ->where('type', 'reviewer')
    //                 ->get()
    //                 ->pluck('projectData.id')
    //                 ->filter();

    //             $entry->reviewer_project_ids = $reviewerAssignments;
    //             $reviewerCount = $reviewerAssignments->count();
    //             $reviewerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereHas('employee_rejected', function ($query) {
    //                     $query->where('status', '!=', 'rejected');
    //                 })
    //                 ->where('type', 'reviewer')->count();
    //             $reviewerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['on_going', 'to_do'])->where('type', 'reviewer')->count();
    //             $reviewerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->where('status', 'need_support')->where('type', 'reviewer')->count();
    //             $reviewerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['correction', 'plag_correction'])->where('type', 'reviewer')->count();
    //         }

    //         if (in_array('11', $positions)) {
    //             $statisticanAssignment = ProjectAssignDetails::with(['projectData'])->where('assign_user', $emp_id)->where('type', 'statistican')
    //                 ->get()
    //                 ->pluck('projectData.id')
    //                 ->filter();
    //             $entry->statistican_project_ids = $statisticanAssignment;
    //             $statisticanCount = $statisticanAssignment->count();
    //             $statisticanPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereHas('employee_rejected', function ($query) {
    //                     $query->where('status', '!=', 'rejected');
    //                 })

    //                 ->where('type', 'statistican')->count();
    //             $statisticanOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['on_going', 'to_do'])->where('type', 'statistican')->count();
    //             $statisticanCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['correction', 'plag_correction'])->where('type', 'statistican')->count();
    //             $statisticanNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->where('status', 'need_support')->where('type', 'statistican')->count();
    //         }

    //         $projectlist = ProjectAssignDetails::with(['UserDate', 'projectData'])
    //             ->where('assign_user', $emp_id)
    //             ->whereIn('project_id', $writerDataId)
    //             ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //             ->whereHas('projectData', function ($query) {
    //                 $query->where('process_status', '!=', 'completed')
    //                     ->where('is_deleted', 0);
    //             })
    //             // Only apply employee_rejected filter if there are any related records
    //             ->where(function ($query) {
    //                 $query->whereDoesntHave('employee_rejected')
    //                     ->orWhereHas('employee_rejected', function ($subQuery) {
    //                         $subQuery->where('status', '!=', 'rejected');
    //                     });
    //             })
    //             ->orderBy('id', 'desc')
    //             ->get()
    //             ->unique('project_id')
    //             ->values();

    //         $positionWiseCompletion = [];

    //         $requiredPositions = ['7' => 'writer', '8' => 'reviewer', '11' => 'statistican'];

    //         $filteredPositions = array_filter($requiredPositions, function ($key) use ($positions) {
    //             return in_array($key, $positions);
    //         }, ARRAY_FILTER_USE_KEY);

    //         foreach ($projectlist as $project) {
    //             $empposition = isset($project->UserDate->position) ? $project->UserDate->position : null;
    //             $positions = isset($empposition) ? explode(',', $empposition) : [];

    //             $statusType = $project->type;

    //             $statusDateTime = new \DateTime($project->projectDuration);
    //             $completedDateTime = new \DateTime($project->updated_at);
    //             $interval = $statusDateTime->diff($completedDateTime);

    //             $daysDifference = $interval->days + 1;

    //             foreach ($positions as $position) {
    //                 $position = trim($position);

    //                 // Ensure only 'reviewer', 'writer', or 'statistican' is counted when applicable
    //                 if (! isset($requiredPositions[$position]) || $requiredPositions[$position] !== $statusType) {
    //                     continue;
    //                 }

    //                 if (! isset($positionWiseCompletion[$position])) {
    //                     $positionWiseCompletion[$position] = [
    //                         'completed_in_4_days' => 0,
    //                         'completed_in_5_to_8_days' => 0,
    //                         'completed_in_more_than_8_days' => 0,
    //                     ];
    //                 }

    //                 if ($daysDifference < 4) {
    //                     $positionWiseCompletion[$position]['completed_in_4_days']++;
    //                 } elseif ($daysDifference >= 5 && $daysDifference <= 8) {
    //                     $positionWiseCompletion[$position]['completed_in_5_to_8_days']++;
    //                 } else {
    //                     $positionWiseCompletion[$position]['completed_in_more_than_8_days']++;
    //                 }
    //             }
    //             // }
    //         }

    //         // Ensure all required positions exist with default values
    //         foreach ($filteredPositions as $position => $type) {
    //             if (! isset($positionWiseCompletion[$position])) {
    //                 $positionWiseCompletion[$position] = [
    //                     'completed_in_4_days' => 0,
    //                     'completed_in_5_to_8_days' => 0,
    //                     'completed_in_more_than_8_days' => 0,
    //                 ];
    //             }
    //         }

    //         // Add counts to response
    //         $entry->writer_count = $writerCount;
    //         $entry->reviewer_count = $reviewerCount;
    //         // $entry->journal_count = $journalCount;
    //         $entry->statistican_count = $statisticanCount;

    //         $entry->writerPendingCount = $writerPendingCount;
    //         $entry->reviewerPendingCount = $reviewerPendingCount;
    //         // $entry->journalPendingCount = $journalPendingCount;
    //         $entry->statisticanPendingCount = $statisticanPendingCount;
    //         $entry->statisticanOngoingCount = $statisticanOngoingCount;

    //         $entry->writerOngoingCount = $writerOngoingCount;
    //         $entry->reviewerOngoingCount = $reviewerOngoingCount;
    //         $entry->writerNeedCount = $writerNeedCount;
    //         $entry->reviewerNeedCount = $reviewerNeedCount;
    //         $entry->statisticanNeedCount = $statisticanNeedCount;
    //         // $entry->journalNeedCount = 0;
    //         //$entry->journalOngoingCount = 0;

    //         $entry->writerCorrectionCount = $writerCorrectionCount;
    //         $entry->reviewerCorrectionCount = $reviewerCorrectionCount;
    //         $entry->statisticanCorrectionCount = $statisticanCorrectionCount;
    //         // $entry->journalCorrectionCount = 0;

    //         $entry->completed_in_4_days = $completedIn4Days;
    //         $entry->completed_in_5_to_8_days = $completedIn5To8Days;
    //         $entry->completed_in_more_than_8_days = $completedInMoreThan8Days;
    //         $entry->positionWiseCompletion = $positionWiseCompletion;
    //     }

    //     $filteredResultsInhouse = $totalProjectsInhouse->filter(function ($entry) {
    //         return $entry->writer_count > 0 || $entry->reviewer_count > 0 || $entry->statistican_count > 0;
    //     })->values();

    //     //freelancer
    //     $totalProjectsFreelancer = People::select('id', 'position', 'employee_name', 'employee_type')->with(['createdByUser'])
    //         ->where('position', '!=', 'Admin')
    //         ->where('employee_type', '=', 'freelancers')
    //         ->whereIn('position', [7, 8, 10, 11])
    //         ->get()
    //         ->map(function ($person) {
    //             $person->created_by_users = $person->created_by_users;

    //             return $person;
    //         });

    //     // Loop through each person and count based on their position
    //     foreach ($totalProjectsFreelancer as $entry) {
    //         $emp_pos = $entry->position;
    //         $emp_id = $entry->id;

    //         // Convert position string to an array
    //         $positions = explode(',', $emp_pos);

    //         // Initialize count variables
    //         $writerCount = $reviewerCount = $journalCount = $statisticanCount = 0;
    //         $writerPendingCount = $reviewerPendingCount = $journalPendingCount = $statisticanPendingCount = 0;
    //         $writerOngoingCount = $reviewerOngoingCount = $writerNeedCount = $reviewerNeedCount = 0;
    //         $writerCorrectionCount = $reviewerCorrectionCount = $statisticanOngoingCount = $statisticanCorrectionCount = 0;

    //         // Initialize count variables for each role
    //         $completedProjects = 0;
    //         $projectlist = [];

    //         // Fetch project list based on all positions
    //         $writerDataId = ProjectAssignDetails::where('assign_user', $emp_id)->get()->pluck('project_id')->toArray();

    //         if (in_array('7', $positions)) {
    //             $writerAssignment = ProjectAssignDetails::with(['projectData'])->where('assign_user', $emp_id)
    //                 ->get()
    //                 ->pluck('projectData.id')
    //                 ->filter();

    //             $entry->writer_project_ids = $writerAssignment;
    //             $writerCount = $writerAssignment->count();
    //             $writerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereHas('employee_rejected', function ($query) {
    //                     $query->where('status', '!=', 'rejected');
    //                 })
    //                 ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //                 ->count();
    //             $writerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['on_going', 'to_do'])->count();
    //             $writerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->where('status', 'need_support')->count();
    //             $writerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['correction', 'plag_correction'])->count();
    //         }

    //         if (in_array('8', $positions)) {
    //             $reviewerAssignment = ProjectAssignDetails::with(['projectData'])->where('assign_user', $emp_id)
    //                 ->get()
    //                 ->pluck('projectData.id')
    //                 ->filter();

    //             $entry->reviewer_project_ids = $reviewerAssignment;
    //             $reviewerCount = $reviewerAssignment->count();
    //             $reviewerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereHas('employee_rejected', function ($query) {
    //                     $query->where('status', '!=', 'rejected');
    //                 })
    //                 ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //                 ->count();
    //             $reviewerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['on_going', 'to_do'])->count();
    //             $reviewerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->where('status', 'need_support')->count();
    //             $reviewerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['correction', 'plag_correction'])->count();
    //         }

    //         if (in_array('11', $positions)) {
    //             $statisticanAssignment = ProjectAssignDetails::with(['projectData'])->where('assign_user', $emp_id)
    //                 ->get()
    //                 ->pluck('projectData.id')
    //                 ->filter();

    //             $entry->statistican_project_ids = $statisticanAssignment;
    //             $statisticanCount = $statisticanAssignment->count();
    //             $statisticanPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereHas('employee_rejected', function ($query) {
    //                     $query->where('status', '!=', 'rejected');
    //                 })
    //                 ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //                 ->count();
    //             $statisticanOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['on_going', 'to_do'])->count();
    //             $statisticanCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })
    //                 ->whereIn('status', ['correction', 'plag_correction'])->count();
    //         }

    //         if (in_array('10', $positions)) {
    //             $journalCount = ProjectAssignDetails::where('assign_user', $emp_id)->count();
    //             $journalPendingCount = 0;
    //             $journalOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)->where('status', 'on_going')->count();
    //             $journalCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['correction_1'])->count();
    //         }

    //         $projectlist = ProjectAssignDetails::with(['UserDate', 'projectData'])
    //             ->where('assign_user', $emp_id)
    //             // ->whereIn('status', ['to_do', 'on_going'])
    //             ->whereIn('project_id', $writerDataId)
    //             ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //             ->whereHas('projectData', function ($query) {
    //                 $query->where('process_status', '!=', 'completed')
    //                     ->where('is_deleted', 0);
    //             })
    //             ->whereHas('employee_rejected', function ($query) {
    //                 $query->where('status', '!=', 'rejected');
    //             })
    //             ->orderBy('id', 'desc')
    //             ->get()
    //             ->unique('project_id')
    //             ->values();
    //         $positionWiseCompletion = [];

    //         $requiredPositions = ['7' => 'writer', '8' => 'reviewer', '11' => 'statistican'];

    //         $filteredPositions = array_filter($requiredPositions, function ($key) use ($positions) {
    //             return in_array($key, $positions);
    //         }, ARRAY_FILTER_USE_KEY);

    //         foreach ($projectlist as $project) {
    //             $empposition = isset($project->UserDate->position) ? $project->UserDate->position : null;
    //             $positions = isset($empposition) ? explode(',', $empposition) : [];

    //             $statusType = $project->type;

    //             $statusDateTime = new \DateTime($project->projectDuration);
    //             $completedDateTime = new \DateTime($project->updated_at);
    //             $interval = $statusDateTime->diff($completedDateTime);

    //             $daysDifference = $interval->days + 1;

    //             foreach ($positions as $position) {
    //                 $position = trim($position);

    //                 // Ensure only 'reviewer', 'writer', or 'statistican' is counted when applicable
    //                 if (! isset($requiredPositions[$position]) || $requiredPositions[$position] !== $statusType) {
    //                     continue;
    //                 }

    //                 if (! isset($positionWiseCompletion[$position])) {
    //                     $positionWiseCompletion[$position] = [
    //                         'completed_in_4_days' => 0,
    //                         'completed_in_5_to_8_days' => 0,
    //                         'completed_in_more_than_8_days' => 0,
    //                     ];
    //                 }
    //                 if ($daysDifference < 4) {
    //                     $positionWiseCompletion[$position]['completed_in_4_days']++;
    //                 } elseif ($daysDifference >= 5 && $daysDifference <= 8) {
    //                     $positionWiseCompletion[$position]['completed_in_5_to_8_days']++;
    //                 } else {
    //                     $positionWiseCompletion[$position]['completed_in_more_than_8_days']++;
    //                 }
    //             }
    //             // }
    //         }

    //         foreach ($filteredPositions as $position => $type) {
    //             if (! isset($positionWiseCompletion[$position])) {
    //                 $positionWiseCompletion[$position] = [
    //                     'completed_in_4_days' => 0,
    //                     'completed_in_5_to_8_days' => 0,
    //                     'completed_in_more_than_8_days' => 0,
    //                 ];
    //             }
    //         }

    //         // Add the counts to the person's data for response
    //         $entry->writer_count = $writerCount;
    //         $entry->reviewer_count = $reviewerCount;
    //         $entry->journal_count = $journalCount;
    //         $entry->statistican_count = $statisticanCount;

    //         $entry->writerPendingCount = $writerPendingCount;
    //         $entry->reviewerPendingCount = $reviewerPendingCount;
    //         $entry->journalPendingCount = $journalPendingCount;
    //         $entry->statisticanPendingCount = $statisticanPendingCount;
    //         $entry->statisticanOngoingCount = $statisticanOngoingCount;

    //         $entry->writerOngoingCount = $writerOngoingCount;
    //         $entry->reviewerOngoingCount = $reviewerOngoingCount;
    //         $entry->writerNeedCount = $writerNeedCount;
    //         $entry->reviewerNeedCount = $reviewerNeedCount;

    //         $entry->writerCorrectionCount = $writerCorrectionCount;
    //         $entry->reviewerCorrectionCount = $reviewerCorrectionCount;
    //         $entry->statisticanCorrectionCount = $statisticanCorrectionCount;

    //         $entry->completed_in_4_days = $completedIn4Days;
    //         $entry->completed_in_5_to_8_days = $completedIn5To8Days;
    //         $entry->completed_in_more_than_8_days = $completedInMoreThan8Days;
    //         $entry->positionWiseCompletion = $positionWiseCompletion;
    //     }

    //     $filteredResultsfreelancer = $totalProjectsFreelancer->filter(function ($entry) {
    //         return $entry->writer_count > 0 || $entry->reviewer_count > 0 || $entry->statistican_count > 0;
    //     })->values();
    //     //thesis project
    //     // $allWriterData = [];
    //     $allWriterData = People::select('id', 'position', 'employee_name', 'employee_type')->with(['createdByUser'])
    //         ->where('position', '!=', 'Admin')
    //         ->whereIn('position', [7, 8, 11])
    //         ->get()
    //         ->map(function ($person) {
    //             $person->created_by_users = $person->created_by_users;

    //             return $person;
    //         });

    //     foreach ($allWriterData as $entry) {
    //         $emp_pos = $entry->position;
    //         $emp_id = $entry->id;
    //         $positions = explode(',', $emp_pos);
    //         $writerCount = $reviewerCount = $journalCount = $statisticanCount = 0;
    //         $writerPendingCount = $reviewerPendingCount = $journalPendingCount = $statisticanPendingCount = 0;
    //         $writerOngoingCount = $reviewerOngoingCount = $writerNeedCount = $reviewerNeedCount = 0;
    //         $writerCorrectionCount = $reviewerCorrectionCount = $statisticanOngoingCount = $statisticanCorrectionCount = 0;

    //         // Initialize count variables for each role
    //         $completedProjects = 0;
    //         $projectlist = [];

    //         // Fetch project list based on all positions
    //         $writerDataId = ProjectAssignDetails::where('assign_user', $emp_id)->get()->pluck('project_id')->toArray();

    //         // Process position-wise counts
    //         if (in_array('7', $positions)) {
    //             $writerAssignment = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereHas('projectData', function ($query) {

    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0);
    //                     // ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->where('type', 'writer')
    //                 ->get()
    //                 ->pluck('projectData.id')
    //                 ->filter();

    //             $entry->writer_project_ids = $writerAssignment;
    //             $writerCount = $writerAssignment->count();
    //             // $writerCount = ProjectAssignDetails::where('assign_user', $emp_id)->count();
    //             $writerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //                 ->where('type', 'writer')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('process_status', '!=', 'completed')
    //                         ->where('is_deleted', 0);
    //                 })

    //                 ->count();
    //             $writerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['on_going', 'to_do'])->where('type', 'writer')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0)
    //                         ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->count();
    //             $writerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)->where('status', 'need_support')->where('type', 'writer')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0)
    //                         ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->count();
    //             $writerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['correction', 'plag_correction'])->where('type', 'writer')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0)
    //                         ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->count();
    //         }

    //         if (in_array('8', $positions)) {
    //             $reviewerAssignment = ProjectAssignDetails::where('assign_user', $emp_id)->where('type', 'reviewer')

    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0);
    //                 })

    //                 ->get()
    //                 ->pluck('projectData.id')
    //                 ->filter();

    //             $entry->reviewer_project_ids = $reviewerAssignment;
    //             $reviewerCount = $reviewerAssignment->count();

    //             $reviewerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //                 ->where('type', 'reviewer')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0)
    //                         ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->count();
    //             $reviewerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['on_going', 'to_do'])->where('type', 'reviewer')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0)
    //                         ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->count();
    //             $reviewerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)->where('status', 'need_support')->where('type', 'reviewer')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0)
    //                         ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->count();
    //             $reviewerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['correction', 'plag_correction'])->where('type', 'reviewer')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0)
    //                         ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->count();
    //         }

    //         if (in_array('11', $positions)) {
    //             $statisticanAssignment = ProjectAssignDetails::where('assign_user', $emp_id)->where('type', 'statistican')

    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0);
    //                     // ->where('process_status', '!=', 'completed');
    //                 })

    //                 ->get()
    //                 ->pluck('projectData.id')
    //                 ->filter();

    //             $entry->statistican_project_ids = $statisticanAssignment;
    //             $statisticanCount = $statisticanAssignment->count();
    //             // $statisticanCount = ProjectAssignDetails::where('assign_user', $emp_id)->count();
    //             $statisticanPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
    //                 ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //                 ->where('type', 'statistican')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0)
    //                         ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->count();
    //             $statisticanOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['on_going', 'to_do'])->where('type', 'statistican')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0)
    //                         ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->count();
    //             $statisticanNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)->where('status', 'need_support')->where('type', 'statistican')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0)
    //                         ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->count();

    //             $statisticanCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['correction', 'plag_correction'])->where('type', 'statistican')
    //                 ->whereHas('projectData', function ($query) {
    //                     $query->where('type_of_work', 'thesis')
    //                         ->where('is_deleted', 0)
    //                         ->where('process_status', '!=', 'completed');
    //                 })
    //                 ->count();
    //         }

    //         $projectlist = ProjectAssignDetails::with(['UserDate', 'projectData'])
    //             ->where('assign_user', $emp_id)
    //             // ->whereIn('status', ['to_do', 'on_going'])
    //             ->whereIn('project_id', $writerDataId)
    //             ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
    //             ->whereHas('projectData', function ($query) {
    //                 $query->where('type_of_work', 'thesis')
    //                     ->where('process_status', '!=', 'completed')
    //                     ->where('is_deleted', 0);
    //             })
    //             ->whereHas('employee_rejected', function ($query) {
    //                 $query->where('status', '!=', 'rejected');
    //             })
    //             ->orderBy('id', 'desc')
    //             ->get()
    //             ->unique('project_id')
    //             ->values();

    //         $positionWiseCompletion = [];
    //         $requiredPositions = ['7' => 'writer', '8' => 'reviewer', '11' => 'statistican'];

    //         $filteredPositions = array_filter($requiredPositions, function ($key) use ($positions) {
    //             return in_array($key, $positions);
    //         }, ARRAY_FILTER_USE_KEY);

    //         foreach ($projectlist as $project) {
    //             $empposition = isset($project->UserDate->position) ? $project->UserDate->position : null;
    //             $positions = isset($empposition) ? explode(',', $empposition) : [];

    //             $statusType = $project->type;

    //             $statusDateTime = new \DateTime($project->projectDuration);
    //             $completedDateTime = new \DateTime($project->updated_at);
    //             $interval = $statusDateTime->diff($completedDateTime);

    //             $daysDifference = $interval->days + 1;

    //             foreach ($positions as $position) {
    //                 $position = trim($position);

    //                 if (! isset($requiredPositions[$position]) || $requiredPositions[$position] !== $statusType) {
    //                     continue;
    //                 }

    //                 if (! isset($positionWiseCompletion[$position])) {
    //                     $positionWiseCompletion[$position] = [
    //                         'completed_in_4_days' => 0,
    //                         'completed_in_5_to_8_days' => 0,
    //                         'completed_in_more_than_8_days' => 0,
    //                     ];
    //                 }

    //                 if ($daysDifference < 4) {
    //                     $positionWiseCompletion[$position]['completed_in_4_days']++;
    //                 } elseif ($daysDifference >= 5 && $daysDifference <= 8) {
    //                     $positionWiseCompletion[$position]['completed_in_5_to_8_days']++;
    //                 } else {
    //                     $positionWiseCompletion[$position]['completed_in_more_than_8_days']++;
    //                 }
    //             }
    //             // }
    //         }

    //         foreach ($filteredPositions as $position => $type) {
    //             if (! isset($positionWiseCompletion[$position])) {
    //                 $positionWiseCompletion[$position] = [
    //                     'completed_in_4_days' => 0,
    //                     'completed_in_5_to_8_days' => 0,
    //                     'completed_in_more_than_8_days' => 0,
    //                 ];
    //             }
    //         }

    //         // Add the counts to the person's data for response
    //         $entry->writer_count = $writerCount;
    //         $entry->reviewer_count = $reviewerCount;
    //         // $entry->journal_count = $journalCount;
    //         $entry->statistican_count = $statisticanCount;

    //         $entry->writerPendingCount = $writerPendingCount;
    //         $entry->reviewerPendingCount = $reviewerPendingCount;
    //         // $entry->journalPendingCount = $journalPendingCount;
    //         $entry->statisticanPendingCount = $statisticanPendingCount;
    //         $entry->statisticanNeedCount = $statisticanNeedCount;
    //         $entry->statisticanOngoingCount = $statisticanOngoingCount;

    //         $entry->writerOngoingCount = $writerOngoingCount;
    //         $entry->reviewerOngoingCount = $reviewerOngoingCount;
    //         $entry->writerNeedCount = $writerNeedCount;
    //         $entry->reviewerNeedCount = $reviewerNeedCount;

    //         $entry->writerCorrectionCount = $writerCorrectionCount;
    //         $entry->reviewerCorrectionCount = $reviewerCorrectionCount;
    //         $entry->statisticanCorrectionCount = $statisticanCorrectionCount;

    //         $entry->completed_in_4_days = $completedIn4Days;
    //         $entry->completed_in_5_to_8_days = $completedIn5To8Days;
    //         $entry->completed_in_more_than_8_days = $completedInMoreThan8Days;
    //         $entry->positionWiseCompletion = $positionWiseCompletion;
    //     }

    //     $allWriterData_thesis = $allWriterData->filter(function ($entry) {
    //         return $entry->writer_count > 0 || $entry->reviewer_count > 0 || $entry->statistican_count > 0;
    //     })->values();

    //     $urgentDataList = EntryProcessModel::select('id', 'journal', 'writer', 'statistican', 'reviewer', 'hierarchy_level', 'project_id')->where('hierarchy_level', 'urgent_important')
    //         ->where('is_deleted', 0)
    //         ->where('process_status', '!=', 'completed')
    //         ->whereYear('entry_date', $currentYear)
    //         ->orderBy('id', 'desc')
    //         ->get();

    //     // Count the urgent data
    //     $urgentDataListCount = $urgentDataList->count();

    //     $projectdelayDataList = EntryProcessModel::with(['paymentProcess', 'instituteInfo', 'departmentInfo', 'professionInfo'])->select(
    //         'id',
    //         'title',
    //         'institute',
    //         'department',
    //         'entry_date',
    //         'profession',
    //         'client_name',
    //         DB::raw("CONCAT(DATEDIFF(projectduration, created_at), ' days ', MOD(TIMESTAMPDIFF(HOUR, created_at, projectduration), 24), ' hrs') AS projectduration"),
    //         'hierarchy_level',
    //         'project_id'
    //     )
    //         ->where('projectduration', '<', $currentDate)
    //         ->where('is_deleted', 0)
    //         ->where('process_status', '!=', 'completed')
    //         ->whereYear('entry_date', $currentYear)
    //         ->orderBy('id', 'desc')
    //         ->get();

    //     $projectdelayDataCount = $projectdelayDataList->count();

    //     // Prepare response data
    //     $responseData = [
    //         // 'peoplewise' => $totalProjects,
    //         'peopleInhouse' => $filteredResultsInhouse,
    //         'peopleExternal' => $filteredResultsfreelancer,
    //         'peopleWriterExternal' => $allWriterData_thesis,
    //         'typeofwork' => $typeOfWorkCounts,

    //         'process_staus' => $processStatusCounts,
    //         'journal_status' => $result,
    //         'completedCount' => $completedCounts,
    //         'emergencywork' => $urgentImportantCount,

    //         'not_assigned' => $notAssignedCount,
    //         'project_delaycount' => $projectdelayDataCount,

    //         'freelancet_payment_count' => $freelancerPaymentCount,

    //         'total_count' => $totalCount,
    //         'position' => $position,

    //         'monthwiseData' => $monthwiseData,

    //         'paymentStatusCounts' => $paymentStatusCounts,
    //         'inProgress' => $inProgress,
    //         'inProgressCount' => $inProgressCount,
    //         'urgentDataList' => $urgentDataList,
    //         'urgentDataListCount' => $urgentDataListCount,
    //         'projectdelayDataList' => $projectdelayDataList,
    //         'projectdelayDataCount' => $projectdelayDataCount,
    //         'freelancerProjecctList' => $freelancers,
    //         'freelancerProjecctCount' => $freelancerPaymentCount,
    //         'projectStatusList' => $projectStatusList,
    //         'projectStatusCount' => $projectStatusCount,
    //         'tc_to_do_lists' => $todoItems,

    //         'tc_to_do_list_count' => $merged_to_do_list_count,

    //         'inToDoList' => $notAssignedProjects,
    //         'inToDoListCount' => $inToDoListCount,

    //         'revert_writer' => $revert_writer,

    //         'revert' => $revertdetails,
    //         'TcNotAssignedCount' => $hasRoleTrueCount,
    //         'tcnotAssigned' => $TcNotAssigned,
    //         'notAssigned' => $notAssignedProjects,
    //     ];

    //     return response()->json($responseData);

    //     return [];
    // }

    //common for 3 dasboard
    public function inhouseExternal(Request $request, $fromDate = null, $toDate = null)
    {
        // dd($fromDate, $toDate);
        $currentYear = date('Y');
        if (! $fromDate) {
            $fromDate = date('Y-m-d');
        }
        if (! $toDate) {
            $toDate = date('Y-m-d');
        }
        //people wise response data
        $totalProjects = People::select('id', 'employee_name', 'position')->with(['createdByUser'])
            ->where('position', '!=', 'Admin')
            ->whereIn('position', [7, 8, 10, 11])
            ->where('status', '1')
            ->get();

        // Initialize common queries for EntryProcessModel based on the position
        $entryProcessData = EntryProcessModel::with(['writerData', 'reviewerData', 'statisticanData'])
            ->select('id', 'writer', 'reviewer', 'journal', 'statistican')->whereIn('writer', $totalProjects->pluck('id'))
            ->whereHas('reviewerData', function ($query) use ($totalProjects) {
                $query->where('status', '!=', 'completed')
                    ->whereIn('assign_user', $totalProjects->pluck('id')->toArray());
            })
            ->whereHas('writerData', function ($query) use ($totalProjects) {
                $query->where('status', '!=', 'completed')
                    ->whereIn('assign_user', $totalProjects->pluck('id')->toArray());
            })
            ->whereHas('statisticanData', function ($query) use ($totalProjects) {
                $query->where('status', '!=', 'completed')
                    ->whereIn('assign_user', $totalProjects->pluck('id')->toArray());
            })
            // ->orWhereIn('statistican', $totalProjects->pluck('id'))
            // ->whereYear('entry_date', $currentYear)
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->where('process_status', '!=', 'completed')
            ->where('is_deleted', 0)
            ->get();

        $projectLogsData = ProjectLogs::select('id', 'project_id', 'employee_id', 'status', 'status_date', 'status_type')->whereIn('employee_id', $totalProjects->pluck('id'))
            ->whereHas('entryProcess', function ($query) use ($fromDate, $toDate) {
                $query->where('is_deleted', 0)
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate)
                    ->where('process_status', '!=', 'completed');
            })
            ->where('status', 'to_do')
            ->get();

        // Loop through each person and count based on their position
        foreach ($totalProjects as $entry) {
            $emp_pos = $entry->position;
            $emp_id = $entry->id;

            // Initialize counts
            $writerCount = $reviewerCount = $journalCount = $statisticanCount = 0;
            $writerPendingCount = $reviewerPendingCount = $statisticanPendingCount = $journalPendingCount = 0;
            $completedIn4Days = $completedIn5To8Days = $completedInMoreThan8Days = 0;
            $projectlist = [];

            // Filter EntryProcessData by role
            $filteredEntries = $entryProcessData->filter(function ($item) use ($emp_id, $emp_pos) {
                switch ($emp_pos) {
                    case 7:
                        return $item->writerData->assign_user == $emp_id;
                    case 8:
                        return $item->reviewerData->assign_user == $emp_id;
                    case 10:
                        return $item->journalData->assign_user == $emp_id;
                    case 11:
                        return $item->statisticanData->assign_user == $emp_id;
                    default:
                        return false;
                }
            });

            // Calculate counts
            $entry->writer_count = $filteredEntries->filter(function ($item) use ($emp_id) {
                return $item->writerData && $item->writerData->assign_user == $emp_id && $item->process_status !== 'completed';
            })->count();
            $entry->reviewer_count = $filteredEntries->filter(function ($item) use ($emp_id) {
                return $item->reviewerData && $item->reviewerData->assign_user == $emp_id && $item->process_status !== 'completed';
            })->count();
            $entry->journal_count = $filteredEntries->filter(function ($item) use ($emp_id) {
                return $item->journalData && $item->journalData->assign_user == $emp_id && $item->process_status !== 'completed';
            })->count();
            $entry->statistican_count = $filteredEntries->filter(function ($item) use ($emp_id) {
                return $item->statisticanData && $item->statisticanData->assign_user == $emp_id && $item->process_status !== 'completed';
            })->count();

            // Pending counts
            $entry->writerPendingCount = $filteredEntries->filter(function ($item) use ($emp_id) {
                return $item->writerData && $item->writerData->assign_user == $emp_id && $item->process_status !== 'completed';
            })->count();
            $entry->reviewerPendingCount = $filteredEntries->filter(function ($item) use ($emp_id) {
                return $item->reviewerData && $item->reviewerData->assign_user == $emp_id && $item->process_status !== 'completed';
            })->count();
            $entry->journalPendingCount = $filteredEntries->filter(function ($item) use ($emp_id) {
                return $item->journalData && $item->journalData->assign_user == $emp_id && $item->process_status !== 'completed';
            })->count();
            $entry->statisticanPendingCount = $filteredEntries->filter(function ($item) use ($emp_id) {
                return $item->statisticanData && $item->statisticanData->assign_user == $emp_id && $item->process_status !== 'completed';
            })->count();

            // // Get project list for this employee
            $projectlist = $projectLogsData->where('employee_id', $emp_id);

            // Loop through the projects to calculate the date differences
            foreach ($projectlist as $project) {

                $statusDate = Carbon::parse($project->status_date);
                $daysDifference = $statusDate->diffInDays($statusDate);

                if ($daysDifference < 4) {
                    $completedIn4Days++;
                } elseif ($daysDifference >= 5 && $daysDifference <= 8) {
                    $completedIn5To8Days++;
                } elseif ($daysDifference > 8) {
                    $completedInMoreThan8Days++;
                }
            }

            // Add completed days counts to the entry
            $entry->completed_in_4_days = $completedIn4Days;
            $entry->completed_in_5_to_8_days = $completedIn5To8Days;
            $entry->completed_in_more_than_8_days = $completedInMoreThan8Days;
        }

        //inhouse projects
        $totalProjectsInhouse = People::select('id', 'position', 'employee_name', 'employee_type')
            ->where('position', '!=', 'Admin')
            ->where('employee_type', '!=', 'freelancers')
            ->whereIn('position', [7, 8, 11])
            ->where('status', '1')
            ->get()
            ->map(function ($person) {
                $person->created_by_users = $person->created_by_users; // Access the accessor

                return $person;
            });

        foreach ($totalProjectsInhouse as $entry) {
            $emp_pos = $entry->position;
            $emp_id = $entry->id;
            $positions = explode(',', $emp_pos);

            // Initialize count variables
            $writerCount = $reviewerCount = $journalCount = $statisticanCount = 0;
            $writerPendingCount = $reviewerPendingCount = $journalPendingCount = $statisticanPendingCount = 0;
            $writerOngoingCount = $reviewerOngoingCount = $writerNeedCount = $reviewerNeedCount = $statisticanNeedCount = 0;
            $writerCorrectionCount = $reviewerCorrectionCount = $statisticanOngoingCount = $statisticanCorrectionCount = 0;

            $projectlist = [];

            $writerDataId = ProjectAssignDetails::where('assign_user', $emp_id)->get()->pluck('project_id')->toArray();
            // Process position-wise counts
            if (in_array('7', $positions)) {
                $writerAssignment = ProjectAssignDetails::with(['projectData', 'employee_rejected'])
                    ->where('assign_user', $emp_id)
                    ->where('type', 'writer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query

                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->get()
                    ->pluck('projectData.id')
                    ->filter();

                $entry->writer_project_ids = $writerAssignment;
                $writerCount = $writerAssignment->count();
                $writerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereHas('employee_rejected', function ($query) {
                        $query->where('status', '!=', 'rejected');
                    })
                    ->where('type', 'writer')
                    ->count();
                $writerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['on_going', 'to_do'])
                    ->where('type', 'writer')->count();
                $writerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->where('status', 'need_support')
                    ->where('type', 'writer')->count();
                $writerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['correction', 'plag_correction'])
                    ->where('type', 'writer')->count();
            }

            if (in_array('8', $positions)) {
                $reviewerAssignments = ProjectAssignDetails::with(['projectData', 'employee_rejected'])
                    ->where('assign_user', $emp_id)
                    ->where('type', 'reviewer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query

                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->get()
                    ->pluck('projectData.id')
                    ->filter();

                $entry->reviewer_project_ids = $reviewerAssignments;
                $reviewerCount = $reviewerAssignments->count();
                $reviewerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereHas('employee_rejected', function ($query) {
                        $query->where('status', '!=', 'rejected');
                    })
                    ->where('type', 'reviewer')->count();
                $reviewerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['on_going', 'to_do'])->where('type', 'reviewer')->count();
                $reviewerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->where('status', 'need_support')->where('type', 'reviewer')->count();
                $reviewerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['correction', 'plag_correction'])->where('type', 'reviewer')->count();
            }

            if (in_array('11', $positions)) {
                $statisticanAssignment = ProjectAssignDetails::with(['projectData'])->where('assign_user', $emp_id)->where('type', 'statistican')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query

                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->get()
                    ->pluck('projectData.id')
                    ->filter();
                $entry->statistican_project_ids = $statisticanAssignment;
                $statisticanCount = $statisticanAssignment->count();
                $statisticanPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereHas('employee_rejected', function ($query) {
                        $query->where('status', '!=', 'rejected');
                    })

                    ->where('type', 'statistican')->count();
                $statisticanOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['on_going', 'to_do'])->where('type', 'statistican')->count();
                $statisticanCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['correction', 'plag_correction'])->where('type', 'statistican')->count();
                $statisticanNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->where('status', 'need_support')->where('type', 'statistican')->count();
            }

            $projectlist = ProjectAssignDetails::with(['UserDate', 'projectData'])
                ->where('assign_user', $emp_id)
                ->whereIn('project_id', $writerDataId)
                ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                    $query->where('process_status', '!=', 'completed')
                        ->where('is_deleted', 0)
                        ->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate);
                })
                // Only apply employee_rejected filter if there are any related records
                ->where(function ($query) {
                    $query->whereDoesntHave('employee_rejected')
                        ->orWhereHas('employee_rejected', function ($subQuery) {
                            $subQuery->where('status', '!=', 'rejected');
                        });
                })
                ->orderBy('id', 'desc')
                ->get()
                ->unique('project_id')
                ->values();

            $positionWiseCompletion = [];

            $requiredPositions = ['7' => 'writer', '8' => 'reviewer', '11' => 'statistican'];

            $filteredPositions = array_filter($requiredPositions, function ($key) use ($positions) {
                return in_array($key, $positions);
            }, ARRAY_FILTER_USE_KEY);

            foreach ($projectlist as $project) {
                $empposition = isset($project->UserDate->position) ? $project->UserDate->position : null;
                $positions = isset($empposition) ? explode(',', $empposition) : [];

                $statusType = $project->type;

                $statusDateTime = new \DateTime($project->projectDuration);
                $completedDateTime = new \DateTime($project->updated_at);
                $interval = $statusDateTime->diff($completedDateTime);

                $daysDifference = $interval->days + 1;

                foreach ($positions as $position) {
                    $position = trim($position);

                    // Ensure only 'reviewer', 'writer', or 'statistican' is counted when applicable
                    if (! isset($requiredPositions[$position]) || $requiredPositions[$position] !== $statusType) {
                        continue;
                    }

                    if (! isset($positionWiseCompletion[$position])) {
                        $positionWiseCompletion[$position] = [
                            'completed_in_4_days' => 0,
                            'completed_in_5_to_8_days' => 0,
                            'completed_in_more_than_8_days' => 0,
                        ];
                    }

                    if ($daysDifference < 4) {
                        $positionWiseCompletion[$position]['completed_in_4_days']++;
                    } elseif ($daysDifference >= 5 && $daysDifference <= 8) {
                        $positionWiseCompletion[$position]['completed_in_5_to_8_days']++;
                    } else {
                        $positionWiseCompletion[$position]['completed_in_more_than_8_days']++;
                    }
                }
                // }
            }

            // Ensure all required positions exist with default values
            foreach ($filteredPositions as $position => $type) {
                if (! isset($positionWiseCompletion[$position])) {
                    $positionWiseCompletion[$position] = [
                        'completed_in_4_days' => 0,
                        'completed_in_5_to_8_days' => 0,
                        'completed_in_more_than_8_days' => 0,
                    ];
                }
            }

            // Add counts to response
            $entry->writer_count = $writerCount;
            $entry->reviewer_count = $reviewerCount;
            // $entry->journal_count = $journalCount;
            $entry->statistican_count = $statisticanCount;

            $entry->writerPendingCount = $writerPendingCount;
            $entry->reviewerPendingCount = $reviewerPendingCount;
            // $entry->journalPendingCount = $journalPendingCount;
            $entry->statisticanPendingCount = $statisticanPendingCount;
            $entry->statisticanOngoingCount = $statisticanOngoingCount;

            $entry->writerOngoingCount = $writerOngoingCount;
            $entry->reviewerOngoingCount = $reviewerOngoingCount;
            $entry->writerNeedCount = $writerNeedCount;
            $entry->reviewerNeedCount = $reviewerNeedCount;
            $entry->statisticanNeedCount = $statisticanNeedCount;
            // $entry->journalNeedCount = 0;
            //$entry->journalOngoingCount = 0;

            $entry->writerCorrectionCount = $writerCorrectionCount;
            $entry->reviewerCorrectionCount = $reviewerCorrectionCount;
            $entry->statisticanCorrectionCount = $statisticanCorrectionCount;
            // $entry->journalCorrectionCount = 0;

            $entry->completed_in_4_days = $completedIn4Days;
            $entry->completed_in_5_to_8_days = $completedIn5To8Days;
            $entry->completed_in_more_than_8_days = $completedInMoreThan8Days;
            $entry->positionWiseCompletion = $positionWiseCompletion;
        }

        $filteredResultsInhouse = $totalProjectsInhouse->filter(function ($entry) {
            return $entry->writer_count > 0 || $entry->reviewer_count > 0 || $entry->statistican_count > 0;
        })->values();

        //freelancer
        $totalProjectsFreelancer = People::select('id', 'position', 'employee_name', 'employee_type')->with(['createdByUser'])
            ->where('position', '!=', 'Admin')
            ->where('employee_type', '=', 'freelancers')
            ->whereIn('position', [7, 8, 10, 11])
            ->where('status', '1')
            ->get()
            ->map(function ($person) {
                $person->created_by_users = $person->created_by_users;

                return $person;
            });

        // Loop through each person and count based on their position
        foreach ($totalProjectsFreelancer as $entry) {
            $emp_pos = $entry->position;
            $emp_id = $entry->id;

            // Convert position string to an array
            $positions = explode(',', $emp_pos);

            // Initialize count variables
            $writerCount = $reviewerCount = $journalCount = $statisticanCount = 0;
            $writerPendingCount = $reviewerPendingCount = $journalPendingCount = $statisticanPendingCount = 0;
            $writerOngoingCount = $reviewerOngoingCount = $writerNeedCount = $reviewerNeedCount = 0;
            $writerCorrectionCount = $reviewerCorrectionCount = $statisticanOngoingCount = $statisticanCorrectionCount = 0;

            // Initialize count variables for each role
            $completedProjects = 0;
            $projectlist = [];

            // Fetch project list based on all positions
            $writerDataId = ProjectAssignDetails::where('assign_user', $emp_id)->get()->pluck('project_id')->toArray();

            if (in_array('7', $positions)) {
                // $writerAssignment = ProjectAssignDetails::with(['projectData'])->where('assign_user', $emp_id)
                //     ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                //         $query->whereDate('entry_date', '>=', $fromDate)
                //             ->whereDate('entry_date', '<=', $toDate);
                //     })
                //     ->get()
                //     ->pluck('projectData.id')
                //     ->filter();
                $writerAssignment = ProjectAssignDetails::with('projectData')
                    ->where('assign_user', $emp_id)
                    ->where('type', 'writer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->get()
                    ->pluck('projectData.id')
                    ->filter()
                    ->unique()
                    ->values();

                $entry->writer_project_ids = $writerAssignment;
                $writerCount = $writerAssignment->count();
                $writerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->where('type', 'writer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereHas('employee_rejected', function ($query) {
                        $query->where('status', '!=', 'rejected');
                    })
                    ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                    ->count();
                $writerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->where('type', 'writer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['on_going', 'to_do'])->count();
                $writerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->where('type', 'writer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->where('status', 'need_support')->count();
                $writerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->where('type', 'writer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['correction', 'plag_correction'])->count();
            }

            if (in_array('8', $positions)) {
                $reviewerAssignment = ProjectAssignDetails::with(['projectData'])->where('assign_user', $emp_id)
                    ->where('type', 'reviewer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->get()
                    ->pluck('projectData.id')
                    ->filter()
                    ->unique()
                    ->values();

                $entry->reviewer_project_ids = $reviewerAssignment;
                $reviewerCount = $reviewerAssignment->count();
                $reviewerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->where('type', 'reviewer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereHas('employee_rejected', function ($query) {
                        $query->where('status', '!=', 'rejected');
                    })
                    ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                    ->count();
                $reviewerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)->where('type', 'reviewer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['on_going', 'to_do'])->count();
                $reviewerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)->where('type', 'reviewer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->where('status', 'need_support')->count();
                $reviewerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)->where('type', 'reviewer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['correction', 'plag_correction'])->count();
            }

            if (in_array('11', $positions)) {
                $statisticanAssignment = ProjectAssignDetails::with(['projectData'])->where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->get()
                    ->pluck('projectData.id')
                    ->filter()
                    ->unique()
                    ->values();

                $entry->statistican_project_ids = $statisticanAssignment;
                $statisticanCount = $statisticanAssignment->count();
                $statisticanPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereHas('employee_rejected', function ($query) {
                        $query->where('status', '!=', 'rejected');
                    })
                    ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                    ->count();
                $statisticanOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['on_going', 'to_do'])->count();
                $statisticanCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->whereIn('status', ['correction', 'plag_correction'])->count();
            }

            if (in_array('10', $positions)) {
                $journalCount = ProjectAssignDetails::where('assign_user', $emp_id)->count();
                $journalPendingCount = 0;
                $journalOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)->where('status', 'on_going')->count();
                $journalCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['correction_1'])->count();
            }

            $projectlist = ProjectAssignDetails::with(['UserDate', 'projectData'])
                ->where('assign_user', $emp_id)
                // ->whereIn('status', ['to_do', 'on_going'])
                ->whereIn('project_id', $writerDataId)
                ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                    $query->where('process_status', '!=', 'completed')
                        ->where('is_deleted', 0)
                        ->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate);
                })
                ->whereHas('employee_rejected', function ($query) {
                    $query->where('status', '!=', 'rejected');
                })
                ->orderBy('id', 'desc')
                ->get()
                ->unique('project_id')
                ->values();
            $positionWiseCompletion = [];

            $requiredPositions = ['7' => 'writer', '8' => 'reviewer', '11' => 'statistican'];

            $filteredPositions = array_filter($requiredPositions, function ($key) use ($positions) {
                return in_array($key, $positions);
            }, ARRAY_FILTER_USE_KEY);

            foreach ($projectlist as $project) {
                $empposition = isset($project->UserDate->position) ? $project->UserDate->position : null;
                $positions = isset($empposition) ? explode(',', $empposition) : [];

                $statusType = $project->type;

                $statusDateTime = new \DateTime($project->projectDuration);
                $completedDateTime = new \DateTime($project->updated_at);
                $interval = $statusDateTime->diff($completedDateTime);

                $daysDifference = $interval->days + 1;

                foreach ($positions as $position) {
                    $position = trim($position);

                    // Ensure only 'reviewer', 'writer', or 'statistican' is counted when applicable
                    if (! isset($requiredPositions[$position]) || $requiredPositions[$position] !== $statusType) {
                        continue;
                    }

                    if (! isset($positionWiseCompletion[$position])) {
                        $positionWiseCompletion[$position] = [
                            'completed_in_4_days' => 0,
                            'completed_in_5_to_8_days' => 0,
                            'completed_in_more_than_8_days' => 0,
                        ];
                    }
                    if ($daysDifference < 4) {
                        $positionWiseCompletion[$position]['completed_in_4_days']++;
                    } elseif ($daysDifference >= 5 && $daysDifference <= 8) {
                        $positionWiseCompletion[$position]['completed_in_5_to_8_days']++;
                    } else {
                        $positionWiseCompletion[$position]['completed_in_more_than_8_days']++;
                    }
                }
                // }
            }

            foreach ($filteredPositions as $position => $type) {
                if (! isset($positionWiseCompletion[$position])) {
                    $positionWiseCompletion[$position] = [
                        'completed_in_4_days' => 0,
                        'completed_in_5_to_8_days' => 0,
                        'completed_in_more_than_8_days' => 0,
                    ];
                }
            }

            // Add the counts to the person's data for response
            $entry->writer_count = $writerCount;
            $entry->reviewer_count = $reviewerCount;
            $entry->journal_count = $journalCount;
            $entry->statistican_count = $statisticanCount;

            $entry->writerPendingCount = $writerPendingCount;
            $entry->reviewerPendingCount = $reviewerPendingCount;
            $entry->journalPendingCount = $journalPendingCount;
            $entry->statisticanPendingCount = $statisticanPendingCount;
            $entry->statisticanOngoingCount = $statisticanOngoingCount;

            $entry->writerOngoingCount = $writerOngoingCount;
            $entry->reviewerOngoingCount = $reviewerOngoingCount;
            $entry->writerNeedCount = $writerNeedCount;
            $entry->reviewerNeedCount = $reviewerNeedCount;

            $entry->writerCorrectionCount = $writerCorrectionCount;
            $entry->reviewerCorrectionCount = $reviewerCorrectionCount;
            $entry->statisticanCorrectionCount = $statisticanCorrectionCount;

            $entry->completed_in_4_days = $completedIn4Days;
            $entry->completed_in_5_to_8_days = $completedIn5To8Days;
            $entry->completed_in_more_than_8_days = $completedInMoreThan8Days;
            $entry->positionWiseCompletion = $positionWiseCompletion;
        }

        $filteredResultsfreelancer = $totalProjectsFreelancer->filter(function ($entry) {
            return $entry->writer_count > 0 || $entry->reviewer_count > 0 || $entry->statistican_count > 0;
        })->values();
        //thesis project
        // $allWriterData = [];
        $allWriterData = People::select('id', 'position', 'employee_name', 'employee_type')->with(['createdByUser'])
            ->where('position', '!=', 'Admin')
            ->whereIn('position', [7, 8, 11])
            ->get()
            ->map(function ($person) {
                $person->created_by_users = $person->created_by_users;

                return $person;
            });

        foreach ($allWriterData as $entry) {
            $emp_pos = $entry->position;
            $emp_id = $entry->id;
            $positions = explode(',', $emp_pos);
            $writerCount = $reviewerCount = $journalCount = $statisticanCount = 0;
            $writerPendingCount = $reviewerPendingCount = $journalPendingCount = $statisticanPendingCount = 0;
            $writerOngoingCount = $reviewerOngoingCount = $writerNeedCount = $reviewerNeedCount = 0;
            $writerCorrectionCount = $reviewerCorrectionCount = $statisticanOngoingCount = $statisticanCorrectionCount = 0;

            // Initialize count variables for each role
            $completedProjects = 0;
            $projectlist = [];

            // Fetch project list based on all positions
            $writerDataId = ProjectAssignDetails::where('assign_user', $emp_id)
                ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {

                    $query->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate);
                })
                ->get()->pluck('project_id')->toArray();

            // Process position-wise counts
            if (in_array('7', $positions)) {
                $writerAssignment = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {

                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                        // ->where('process_status', '!=', 'completed');
                    })
                    ->where('type', 'writer')
                    ->get()
                    ->pluck('projectData.id')
                    ->filter();

                $entry->writer_project_ids = $writerAssignment;
                $writerCount = $writerAssignment->count();
                // $writerCount = ProjectAssignDetails::where('assign_user', $emp_id)->count();
                $writerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                    ->where('type', 'writer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('process_status', '!=', 'completed')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })

                    ->count();
                $writerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['on_going', 'to_do'])->where('type', 'writer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate)
                            ->where('process_status', '!=', 'completed');
                    })
                    ->count();
                $writerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)->where('status', 'need_support')->where('type', 'writer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->where('process_status', '!=', 'completed')
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->count();
                $writerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['correction', 'plag_correction'])->where('type', 'writer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->where('process_status', '!=', 'completed')
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->count();
            }

            if (in_array('8', $positions)) {
                $reviewerAssignment = ProjectAssignDetails::where('assign_user', $emp_id)->where('type', 'reviewer')

                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })

                    ->get()
                    ->pluck('projectData.id')
                    ->filter();

                $entry->reviewer_project_ids = $reviewerAssignment;
                $reviewerCount = $reviewerAssignment->count();

                $reviewerPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                    ->where('type', 'reviewer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->where('process_status', '!=', 'completed')
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->count();
                $reviewerOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['on_going', 'to_do'])->where('type', 'reviewer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->where('process_status', '!=', 'completed')
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->count();
                $reviewerNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)->where('status', 'need_support')->where('type', 'reviewer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->where('process_status', '!=', 'completed')
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->count();
                $reviewerCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['correction', 'plag_correction'])->where('type', 'reviewer')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->where('process_status', '!=', 'completed')
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->count();
            }

            if (in_array('11', $positions)) {
                $statisticanAssignment = ProjectAssignDetails::where('assign_user', $emp_id)->where('type', 'statistican')

                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                        // ->where('process_status', '!=', 'completed');
                    })

                    ->get()
                    ->pluck('projectData.id')
                    ->filter();

                $entry->statistican_project_ids = $statisticanAssignment;
                $statisticanCount = $statisticanAssignment->count();
                // $statisticanCount = ProjectAssignDetails::where('assign_user', $emp_id)->count();
                $statisticanPendingCount = ProjectAssignDetails::where('assign_user', $emp_id)
                    ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                    ->where('type', 'statistican')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->where('process_status', '!=', 'completed')
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->count();
                $statisticanOngoingCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['on_going', 'to_do'])->where('type', 'statistican')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->where('process_status', '!=', 'completed')
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->count();
                $statisticanNeedCount = ProjectAssignDetails::where('assign_user', $emp_id)->where('status', 'need_support')->where('type', 'statistican')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->where('process_status', '!=', 'completed')
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->count();

                $statisticanCorrectionCount = ProjectAssignDetails::where('assign_user', $emp_id)->whereIn('status', ['correction', 'plag_correction'])->where('type', 'statistican')
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('type_of_work', 'thesis')
                            ->where('is_deleted', 0)
                            ->where('process_status', '!=', 'completed')
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->count();
            }

            $projectlist = ProjectAssignDetails::with(['UserDate', 'projectData'])
                ->where('assign_user', $emp_id)
                // ->whereIn('status', ['to_do', 'on_going'])
                ->whereIn('project_id', $writerDataId)
                ->whereIn('status', ['plag_correction', 'on_going', 'to_do', 'correction', 'need_support'])
                ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                    $query->where('type_of_work', 'thesis')
                        ->where('process_status', '!=', 'completed')
                        ->where('is_deleted', 0)
                        ->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate);
                })
                ->whereHas('employee_rejected', function ($query) {
                    $query->where('status', '!=', 'rejected');
                })
                ->orderBy('id', 'desc')
                ->get()
                ->unique('project_id')
                ->values();

            $positionWiseCompletion = [];
            $requiredPositions = ['7' => 'writer', '8' => 'reviewer', '11' => 'statistican'];

            $filteredPositions = array_filter($requiredPositions, function ($key) use ($positions) {
                return in_array($key, $positions);
            }, ARRAY_FILTER_USE_KEY);

            foreach ($projectlist as $project) {
                $empposition = isset($project->UserDate->position) ? $project->UserDate->position : null;
                $positions = isset($empposition) ? explode(',', $empposition) : [];

                $statusType = $project->type;

                $statusDateTime = new \DateTime($project->projectDuration);
                $completedDateTime = new \DateTime($project->updated_at);
                $interval = $statusDateTime->diff($completedDateTime);

                $daysDifference = $interval->days + 1;

                foreach ($positions as $position) {
                    $position = trim($position);

                    if (! isset($requiredPositions[$position]) || $requiredPositions[$position] !== $statusType) {
                        continue;
                    }

                    if (! isset($positionWiseCompletion[$position])) {
                        $positionWiseCompletion[$position] = [
                            'completed_in_4_days' => 0,
                            'completed_in_5_to_8_days' => 0,
                            'completed_in_more_than_8_days' => 0,
                        ];
                    }

                    if ($daysDifference < 4) {
                        $positionWiseCompletion[$position]['completed_in_4_days']++;
                    } elseif ($daysDifference >= 5 && $daysDifference <= 8) {
                        $positionWiseCompletion[$position]['completed_in_5_to_8_days']++;
                    } else {
                        $positionWiseCompletion[$position]['completed_in_more_than_8_days']++;
                    }
                }
                // }
            }

            foreach ($filteredPositions as $position => $type) {
                if (! isset($positionWiseCompletion[$position])) {
                    $positionWiseCompletion[$position] = [
                        'completed_in_4_days' => 0,
                        'completed_in_5_to_8_days' => 0,
                        'completed_in_more_than_8_days' => 0,
                    ];
                }
            }

            // Add the counts to the person's data for response
            $entry->writer_count = $writerCount;
            $entry->reviewer_count = $reviewerCount;
            // $entry->journal_count = $journalCount;
            $entry->statistican_count = $statisticanCount;

            $entry->writerPendingCount = $writerPendingCount;
            $entry->reviewerPendingCount = $reviewerPendingCount;
            // $entry->journalPendingCount = $journalPendingCount;
            $entry->statisticanPendingCount = $statisticanPendingCount;
            $entry->statisticanNeedCount = $statisticanNeedCount;
            $entry->statisticanOngoingCount = $statisticanOngoingCount;

            $entry->writerOngoingCount = $writerOngoingCount;
            $entry->reviewerOngoingCount = $reviewerOngoingCount;
            $entry->writerNeedCount = $writerNeedCount;
            $entry->reviewerNeedCount = $reviewerNeedCount;

            $entry->writerCorrectionCount = $writerCorrectionCount;
            $entry->reviewerCorrectionCount = $reviewerCorrectionCount;
            $entry->statisticanCorrectionCount = $statisticanCorrectionCount;

            $entry->completed_in_4_days = $completedIn4Days;
            $entry->completed_in_5_to_8_days = $completedIn5To8Days;
            $entry->completed_in_more_than_8_days = $completedInMoreThan8Days;
            $entry->positionWiseCompletion = $positionWiseCompletion;
        }

        $allWriterData_thesis = $allWriterData->filter(function ($entry) {
            return $entry->writer_count > 0 || $entry->reviewer_count > 0 || $entry->statistican_count > 0;
        })->values();

        return response()->json([
            'peopleInhouse' => $filteredResultsInhouse,
            'peopleExternal' => $filteredResultsfreelancer,
            'peopleWriterExternal' => $allWriterData_thesis,
        ]);
    }

    // tcDashboard

    public function tcDashboard(Request $request)
    {
        $currentYear = date('Y');
        $position = $request->get('position');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $currentDate = now()->format('Y-m-d');

        // 1. Get total count for current year
        $totalCount = EntryProcessModel::where('is_deleted', 0)
            // ->whereYear('entry_date', $currentYear)
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->count();

        // 2. Get entries for current year with specific columns
        $entries = EntryProcessModel::select(
            'id',
            'type_of_work',
            'project_id',
            'process_status',
            'hierarchy_level',
            'projectduration',
            'created_by'
        )
            ->where('is_deleted', 0)
            // ->whereYear('entry_date', $currentYear)
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->get();
        $entriesTask = EntryProcessModel::select(
            'id',
            'type_of_work',
            'project_id',
            'process_status',
            'hierarchy_level',
            'projectduration',
            'created_by'
        )
            ->where('is_deleted', 0)
            // ->whereYear('entry_date', $currentYear)
            // ->whereRaw("DATE_FORMAT(entry_date, '%Y-%m') = ?", [$selectedMonth])
            ->get();

        $projectIds = $entries->pluck('id')->unique()->toArray();
        $projectIdsTask = $entriesTask->pluck('id')->unique()->toArray();

        // 3. TC To-Do items with complex conditions
        $tc_to_do = ProjectAssignDetails::with([
            'projectData.writerData',
            'projectData.reviewerData',
            'projectData.statisticanData',
            'projectData.tcData',
        ])
            ->where('status', 'correction')
            ->where('type', 'team_coordinator')
            ->orderBy('updated_at', 'desc')
            ->whereHas('projectData', function ($q) {
                $q->where('process_status', '!=', 'completed')
                    ->where('is_deleted', 0);

                // Writer condition based on type_of_work
                $q->where(function ($innerQ) {
                    $innerQ->where(function ($subInnerQ) {
                        // Non-thesis → block to_do & on_going
                        $subInnerQ->where('type_of_work', '!=', 'thesis')
                            ->whereDoesntHave('writerData', function ($subQ) {
                                $subQ->whereIn('status', [
                                    'to_do',
                                    'on_going',
                                    'correction',
                                    'plag_correction',
                                    'rejected',
                                    'revert',
                                    'need_support',
                                ]);
                            });
                    })
                        ->orWhere(function ($subInnerQ) {
                            // Thesis → only block correction, plag_correction, rejected, revert
                            $subInnerQ->where('type_of_work', 'thesis')
                                ->whereDoesntHave('writerData', function ($subQ) {
                                    $subQ->whereIn('status', [
                                        'correction',
                                        'plag_correction',
                                        'rejected',
                                        'revert',
                                        'need_support',
                                    ]);
                                });
                        });
                });

                // Reviewer condition based on type_of_work
                $q->where(function ($innerQ) {
                    $innerQ->where(function ($subInnerQ) {
                        // Non-thesis → block to_do & on_going
                        $subInnerQ->where('type_of_work', '!=', 'thesis')
                            ->whereDoesntHave('reviewerData', function ($subQ) {
                                $subQ->whereIn('status', [
                                    'to_do',
                                    'on_going',
                                    'correction',
                                    'plag_correction',
                                    'rejected',
                                    'revert',
                                    'need_support',
                                ]);
                            });
                    })
                        ->orWhere(function ($subInnerQ) {
                            // Thesis → only block correction, plag_correction, rejected, revert
                            $subInnerQ->where('type_of_work', 'thesis')
                                ->whereDoesntHave('reviewerData', function ($subQ) {
                                    $subQ->whereIn('status', [
                                        'correction',
                                        'plag_correction',
                                        'rejected',
                                        'revert',
                                        'need_support',
                                    ]);
                                });
                        });
                });

                // Statistician condition
                $q->whereDoesntHave('statisticanData', function ($subQ) {
                    $subQ->whereIn('status', [
                        'to_do',
                        'on_going',
                        'correction',
                        'plag_correction',
                        'rejected',
                        'revert',
                        'need_support',
                    ]);
                });

                // Project acceptance status
                $q->whereDoesntHave('projectAcceptStatust', function ($sq) {
                    $sq->where('status', 'rejected');
                });

                // SME data condition
                $q->whereDoesntHave('smeData', function ($subQ) {
                    $subQ->where('status', 'need_support');
                });
            })
            ->get()
            ->unique('project_id')
            ->filter(function ($row) {
                if ($row->projectData->tcData->isNotEmpty()) {
                    return true;
                }

                $writerStatus = optional($row->projectData->writerData->first())->status;
                $reviewerStatus = optional($row->projectData->reviewerData->first())->status;
                $statisticianStatus = optional($row->projectData->statisticanData->first())->status;

                return ! ($writerStatus === 'completed' &&
                    $reviewerStatus === 'completed' &&
                    $statisticianStatus === 'completed');
            })
            ->values();

        // 4. People IDs for SME position
        $peopleIds_sme = People::where('position', '13')
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();

        $projectAssignDetails = ProjectAssignDetails::pluck('project_id')->unique();

        // 5. TC Todo List
        $tcTodoListQuery = EntryProcessModel::with([
            'userData',
            'writerData',
            'reviewerData',
            'statisticanData',
            'journalData',
        ])
            ->where('process_status', 'in_progress')
            // ->whereYear('entry_date', $currentYear)
            ->where('is_deleted', 0)
            ->where('process_status', '!=', 'completed')
            ->whereIn('created_by', $peopleIds_sme)
            ->limit(100);

        if ($projectAssignDetails->isNotEmpty()) {
            $tcTodoListQuery->whereNotIn('id', $projectAssignDetails);
        }

        $tcTodoList = $tcTodoListQuery->orderBy('id', 'desc')->get();

        // 6. Admin Todo List
        $adminTodoListQuery = EntryProcessModel::with([
            'userData',
            'writerData',
            'reviewerData',
            'statisticanData',
            'journalData',
        ])
            ->where('process_status', 'in_progress')
            // ->whereYear('entry_date', $currentYear)
            ->where('is_deleted', 0)
            ->where('process_status', '!=', 'completed')
            ->where('created_by', 9);

        if ($projectAssignDetails->isNotEmpty()) {
            $adminTodoListQuery->whereNotIn('id', $projectAssignDetails);
        }

        $adminTodoList = $adminTodoListQuery->orderBy('id', 'desc')->get();

        // 7. Merge and process todo items
        $todoItems = collect($tcTodoList)
            ->merge(
                collect($adminTodoList)
                    ->filter(function ($item) {
                        return ! empty($item->writerData) ||
                            ! empty($item->reviewerData) ||
                            ! empty($item->statisticanData) ||
                            ! empty($item->journalData);
                    })
            )
            ->merge($tc_to_do)
            ->sortByDesc('updated_at')
            ->map(function ($item) {
                $hasAnyRole = (isset($item->writerData) && $item->writerData->isEmpty()) ||
                    (isset($item->reviewerData) && $item->reviewerData->isEmpty()) ||
                    (isset($item->statisticanData) && $item->statisticanData->isEmpty()) ||
                    (isset($item->journalData) && $item->journalData->isEmpty());

                return [
                    'id' => $item->id ?? null,
                    'project_id' => $item->project_id ?? null,
                    'project_ids' => $item->projectData->project_id ?? null,
                    'hierarchy_level' => $item->hierarchy_level ?? null,
                    'hierarchy_levels' => $item->projectData->hierarchy_level ?? null,
                    'process_status' => $item->process_status ?? null,
                    'process_statuses' => $item->projectData->process_status ?? null,
                    'created_by' => $item->created_by ?? null,
                    'has_role' => $hasAnyRole,
                ];
            })
            ->values();

        $TcNotAssigned = $todoItems->filter(function ($item) {
            return $item['has_role'] === true;
        })->pluck('id')->all();

        $hasRoleTrueCount = count($TcNotAssigned);
        $merged_to_do_list = $tcTodoList->merge($adminTodoList)->merge($tc_to_do);
        $merged_to_do_list_count = $merged_to_do_list->count();

        // 8. Statistician without Writer
        $notAssignedProjects = EntryProcessModel::where('process_status', 'not_assigned')
            ->select('id', 'project_id', 'type_of_work', 'process_status', 'hierarchy_level', 'created_at')
            ->where('is_deleted', 0)
            ->orderBy('updated_at', 'desc')
            ->get();
        $statisticianWithoutWriter = ProjectAssignDetails::with([
            'projectData:id,project_id,type_of_work,process_status,hierarchy_level,created_at',
        ])
            ->where('type', 'team_coordinator')
            ->whereIn('type_sme', ['writer', 'Publication Manager', 'reviewer', '2nd_writer'])
            ->where('status', 'completed')
            ->whereNotIn('status', ['need_support'])
            ->whereHas('projectData', function ($query) {
                $query->where('is_deleted', 0)
                    ->where('process_status', '!=', 'completed')
                    ->whereDoesntHave('writerData', function ($sq) {
                        $sq->whereIn('status', ['correction', 'to_do', 'on_going', 'rejected']);
                    })
                    ->whereDoesntHave('reviewerData', function ($sq) {
                        $sq->whereIn('status', ['correction', 'to_do', 'need_support', 'revert', 'on_going', 'rejected']);
                    });
            })
            ->select('project_id', 'status', 'type', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->unique('project_id');

        // 9. Writer Completed Projects
        $writerCompletedProjects = ProjectAssignDetails::with([
            'projectData:id,project_id,type_of_work,process_status,hierarchy_level,created_at',
        ])
            ->whereIn('status', ['completed'])
            ->where('type', 'writer')
            ->whereIn('project_id', $projectIdsTask)
            ->whereHas('projectData', function ($query) {
                $query->where('is_deleted', 0)
                    ->where('process_status', '!=', 'completed')
                    ->whereDoesntHave('projectAcceptStatust', function ($sq) {
                        $sq->where('status', 'rejected');
                    })
                    ->whereDoesntHave('writerData', function ($sq) {
                        $sq->whereIn('status', ['to_do', 'on_going', 'rejected']);
                    })
                    ->whereDoesntHave('reviewerData', function ($sq) {
                        $sq->whereIn('status', ['to_do', 'on_going', 'correction', 'rejected']);
                    });
            })
            ->select('project_id', 'status', 'type', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $allWriterProjects = $writerCompletedProjects->unique('project_id')->values();
        $writerProjectIds = $allWriterProjects->pluck('project_id')->unique()->toArray();

        $reviewerProjects = ProjectAssignDetails::where('type', 'reviewer')
            ->whereIn('project_id', $writerProjectIds)
            ->pluck('project_id')
            ->unique()
            ->toArray();

        $writerWithoutReviewer = $allWriterProjects->filter(function ($writer) use ($reviewerProjects) {
            $typeOfWork = $writer->projectData->type_of_work ?? null;
            $projectId = $writer->project_id;

            // Count completed writers
            $writerCount = ProjectAssignDetails::where('project_id', $projectId)
                ->where('type', 'writer')
                ->where('status', 'completed')
                ->count();

            // if ($typeOfWork === 'thesis' && $writerCount === 2) {
            //     // Count reviewers assigned
            //     $reviewerCount = ProjectAssignDetails::where('project_id', $projectId)
            //         ->where('type', 'reviewer')
            //         ->count();

            //     return $reviewerCount < 2;
            // }

            return ! in_array($projectId, $reviewerProjects);
        })
            ->unique('project_id')
            ->sortByDesc('updated_at')
            ->values();

        $revert_writer = collect()
            ->merge($writerWithoutReviewer)
            ->merge($statisticianWithoutWriter)
            // ->merge($notAssignedProjects)
            ->sortByDesc('updated_at')
            ->unique('project_id')
            ->values();

        // 10. Revert Details
        $revertdetails = ProjectAssignDetails::with([
            'projectData.writerData',
            'projectData.reviewerData',
            'projectData.statisticanData',
            'projectData.tcData',
        ])
            ->whereIn('project_id', $projectIdsTask)
            ->where('status', 'revert')
            ->orderBy('updated_at', 'desc')
            ->whereHas('projectData', function ($query) {
                $query->where('is_deleted', 0)
                    ->where('process_status', '!=', 'completed')
                    ->whereDoesntHave('projectAcceptStatust', function ($sq) {
                        $sq->where('status', 'rejected');
                    })
                    ->whereDoesntHave('writerData', function ($sq) {
                        $sq->whereIn('status', ['to_do', 'on_going']);
                    })
                    ->whereDoesntHave('reviewerData', function ($sq) {
                        $sq->whereIn('status', ['to_do', 'on_going', 'correction']);
                    })
                    ->whereDoesntHave('statisticanData', function ($sq) {
                        $sq->whereIn('status', ['to_do', 'on_going']);
                    });
            })
            ->get()
            ->unique('project_id');

        // 11. Urgent Data
        $urgentDataList = EntryProcessModel::select(
            'id',
            'journal',
            'writer',
            'statistican',
            'reviewer',
            'hierarchy_level',
            'project_id'
        )
            ->where('hierarchy_level', 'urgent_important')
            ->where('is_deleted', 0)
            ->where('process_status', '!=', 'completed')
            // ->whereYear('entry_date', $currentYear)
            ->orderBy('id', 'desc')
            ->get();

        $urgentDataListCount = $urgentDataList->count();

        // 12. Project Status List
        $projectStatusList = EntryProcessModel::with('projectStatus')
            ->whereHas('projectStatus', function ($query) {
                $query->where('status', 'rejected')
                    ->orderBy('created_at', 'desc');
            })
            ->where('is_deleted', 0)
            ->where('process_status', '!=', 'completed')
            // ->whereYear('entry_date', $currentYear)
            ->orderBy('created_at', 'desc')
            ->get();

        $projectStatusCount = $projectStatusList->count();

        // 13. Initialize counters
        $typeOfWorkCounts = [
            'manuscript' => 0,
            'thesis' => 0,
            'statistics' => 0,
            'presentation' => 0,
            'others' => 0,
        ];

        $processStatusCounts = [
            'not_assigned' => 0,
            'pending_author' => 0,
            'withdrawal' => 0,
            'in_progress' => 0,
            'completed' => 0,
        ];

        $urgentImportantCount = 0;
        $importantNotUrgentCount = 0;
        $urgentNotImportantCount = 0;
        $notUrgentNotImportantCount = 0;
        $notAssignedCount = 0;
        $projectDelayCount = 0;
        $freelancerPaymentCount = 0;
        $writerStatusCounts = [];
        $reviewerStatusCounts = [];
        $journalStatusCounts = [];
        $completedCounts = [];
        $assignprojectIds = [];
        $freelancers = [];

        // 14. Process entries for counts
        foreach ($entries as $entry) {
            // Type of work count
            if (isset($typeOfWorkCounts[$entry->type_of_work])) {
                $typeOfWorkCounts[$entry->type_of_work]++;
            } else {
                $typeOfWorkCounts['others']++;
            }

            // Process status count
            if (isset($processStatusCounts[$entry->process_status])) {
                $processStatusCounts[$entry->process_status]++;
            }

            // Journal status count (if needed)
            $journalStatusCounts[$entry->process_status] = ($journalStatusCounts[$entry->process_status] ?? 0) + 1;

            // Completed counts by type
            if ($entry->process_status === 'completed') {
                $completedCounts[$entry->type_of_work] = ($completedCounts[$entry->type_of_work] ?? 0) + 1;
            }

            // Hierarchy level counts
            switch ($entry->hierarchy_level) {
                case 'urgent_important':
                    if ($entry->process_status !== 'completed') {
                        $urgentImportantCount++;
                    }
                    break;
                case 'important_not_urgent':
                    $importantNotUrgentCount++;
                    break;
                case 'urgent_not_important':
                    $urgentNotImportantCount++;
                    break;
                case 'not_urgent_not_important':
                    $notUrgentNotImportantCount++;
                    break;
            }

            // Not assigned count
            if ($entry->process_status === 'not_assigned') {
                $notAssignedCount++;
            }

            // Project delay check
            $projectstatus = ProjectViewStatus::where('project_id', $entry->id)
                ->where('project_status', '!=', 'completed')
                ->orderBy('id', 'desc')
                ->first();

            $projectstatus_completeddate = $projectstatus ? $projectstatus->created_date : null;
            $projectDurationDate = $entry->projectduration;

            if ($projectDurationDate && $projectDurationDate < $currentDate) {
                $projectDelayCount++;
            }

            // Writer/Reviewer status counts for manuscript
            if ($entry->type_of_work === 'manuscript') {
                $assignProject = ProjectAssignDetails::select('status', 'type')
                    ->where('project_id', $entry->id)
                    ->get();

                foreach ($assignProject as $project) {
                    if ($project->type === 'writer') {
                        $writerStatusCounts[$project->status] = ($writerStatusCounts[$project->status] ?? 0) + 1;
                    }

                    if ($project->type === 'reviewer') {
                        $reviewerStatusCounts[$project->status] = ($reviewerStatusCounts[$project->status] ?? 0) + 1;
                    }
                }
            }

            // Freelancer assignment
            $assignproject = ProjectAssignDetails::where('project_id', $entry->id)
                ->pluck('assign_user')
                ->filter()
                ->unique()
                ->toArray();

            if (! empty($assignproject)) {
                $assignprojectIds[$entry->id] = $assignproject;
            }
        }

        // 15. Freelancer payment count
        if (! empty($assignprojectIds)) {
            $allAssignUserIds = array_unique(array_merge(...array_values($assignprojectIds)));

            if (! empty($allAssignUserIds)) {
                $userhrms = DB::connection('mysql_medics_hrms')
                    ->table('employee_details')
                    ->where('employee_type', 'freelancers')
                    ->whereIn('id', $allAssignUserIds)
                    ->where('status', '1')
                    ->get();

                $freelancersById = $userhrms->keyBy('id');
                $processedProjectIds = [];

                foreach ($entries as $entry) {
                    if (
                        ! isset($assignprojectIds[$entry->id]) ||
                        in_array($entry->project_id, $processedProjectIds)
                    ) {
                        continue;
                    }

                    foreach ($assignprojectIds[$entry->id] as $freelancerId) {
                        if (isset($freelancersById[$freelancerId])) {
                            $freelancerPaymentCount++;
                            $processedProjectIds[] = $entry->project_id;

                            $freelancers[] = [
                                'id' => $entry->id,
                                'project_id' => $entry->project_id,
                                'hierarchy_level' => $entry->hierarchy_level,
                                'type_of_work' => $entry->type_of_work,
                            ];
                            break;
                        }
                    }
                }
            }
        }

        // 16. Project Delay Data List
        $projectdelayDataList = EntryProcessModel::with([
            'paymentProcess',
            'instituteInfo',
            'departmentInfo',
            'professionInfo',
        ])
            ->select(
                'id',
                'title',
                'institute',
                'department',
                'entry_date',
                'profession',
                'client_name',
                // DB::raw("CONCAT(DATEDIFF(projectduration, created_at), ' days ', MOD(TIMESTAMPDIFF(HOUR, created_at, projectduration), 24), ' hrs') AS projectduration"),
                DB::raw("CONCAT(DATEDIFF(projectduration, entry_date), ' days') AS projectduration"),
                'hierarchy_level',
                'project_id'
            )
            ->where('projectduration', '<', $currentDate)
            ->where('is_deleted', 0)
            ->where('process_status', '!=', 'completed')
            ->whereYear('entry_date', $currentYear)
            ->orderBy('id', 'desc')
            ->get();

        $projectdelayDataCount = $projectdelayDataList->count();

        // 17. Return response
        return response()->json([
            'tc_to_do_list_count' => $merged_to_do_list_count,
            'tc_to_do_lists' => $todoItems,
            'revert_writer' => $revert_writer,
            'projectStatusList' => $projectStatusList,
            'revert' => $revertdetails,
            'projectdelayDataCount' => $projectdelayDataCount,
            'total_count' => $totalCount,
            'typeofwork' => $typeOfWorkCounts,
            'process_staus' => $processStatusCounts,
            'urgentDataListCount' => $urgentDataListCount,
            'not_assigned' => $notAssignedCount,
            'notAssigned' => $notAssignedProjects,
            'freelancet_payment_count' => $freelancerPaymentCount,
            'inhouseExternal' => $this->inhouseExternal($request, $fromDate, $toDate)->getData(true),
            'monthWiseTable' => $this->monthWiseTable($position, $fromDate, $toDate),
        ]);
    }

    // project manager
    public function pmDashboard(Request $request)
    {
        // dd($request->all());
        $currentYear = date('Y');
        $position = $request->get('position');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $entries = EntryProcessModel::select('id', 'type_of_work', 'project_id', 'process_status', 'hierarchy_level', 'projectduration', 'created_by')->where('is_deleted', 0)->whereDate('entry_date', '>=', $fromDate)
            // ->whereNotIn('process_status', ['completed', 'client_review', 'pending_author'])
            ->whereDate('entry_date', '<=', $toDate)->get();
        $totalCount = EntryProcessModel::select('id')->where('is_deleted', 0)->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)->count();

        $projectStatusList = EntryProcessModel::with('projectStatus') // Just eager-load the relation
            ->whereHas('projectStatus', function ($query) {
                $query->where('status', 'rejected')
                    ->orderBy('created_at', 'desc');
            })
            ->where('is_deleted', 0)
            ->where('process_status', '!=', 'completed')
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->orderBy('created_at', 'desc')
            ->get();

        $projectStatusCount = $projectStatusList->count();

        // Initialize counters
        $typeOfWorkCounts = [
            'manuscript' => 0,
            'thesis' => 0,
            'statistics' => 0,
            'presentation' => 0,
            'others' => 0,
        ];
        $processStatusCounts = [
            'not_assigned' => 0,
            'pending_author' => 0,
            'withdrawal' => 0,
            'in_progress' => 0,
            'completed' => 0,
        ];
        $journalStatusCounts = [
            'submit_to_journal' => 0,
            'submitted' => 0,
            'peer_review' => 0,
            'reviewer_comments' => 0,
            'resubmission' => 0,
            'rejected' => 0,
        ];
        $completedCounts = $typeOfWorkCounts;
        $urgentImportantCount = 0;
        $notAssignedCount = 0;
        $projectDelayCount = 0;
        $freelancerPaymentCount = 0;
        $writerProjectCount = 0;
        $reviewerProjectCount = 0;

        $writerStatusCounts = [
            'completed' => 0,
            'on_going' => 0,
            'correction' => 0,
            'plag_correction' => 0,
        ];

        $reviewerStatusCounts = [
            'completed' => 0,
            'on_going' => 0,
            'correction' => 0,
            'plag_correction' => 0,
        ];

        $paymentStatusCounts = [
            'advance_pending' => 0,
            'partial_payment_pending' => 0,
            'final_payment_pending' => 0,
            'completed' => 0,
        ];

        $paymentEntries = PaymentStatusModel::select('payment_status', 'id')
            ->with(['projectData'])
            ->whereIn('payment_status', [
                'advance_pending',
                'partial_payment_pending',
                'final_payment_pending',
                'completed',
            ])
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->where('is_deleted', 0)
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })

            ->get();

        function getEntryProcessData($statuses, $currentYear, $pid = null)
        {
            $query = EntryProcessModel::select('id', 'hierarchy_level', 'project_id', 'process_status', 'created_by')
                ->with([
                    'userData',
                    'writerData',
                    'reviewerData',
                    'statisticanData',
                    'journalData',
                ])
                ->whereIn('process_status', $statuses)
                // ->whereRaw("DATE_FORMAT(entry_date, '%Y-%m') = ?", [$selectedMonth])
                ->where('is_deleted', 0)
                ->whereNotIn('process_status', ['completed', 'client_review', 'pending_author']);

            // Apply the created_by filter if $pid is provided
            if ($pid !== null) {
                $query = $query->where('created_by', $pid);
            }

            return $query->orderBy('id', 'desc')->get()->map(function ($item) {
                $hasAnyRole =
                    ($item->writerData && $item->writerData->isNotEmpty()) ||
                    ($item->reviewerData && $item->reviewerData->isNotEmpty()) ||
                    ($item->statisticanData && $item->statisticanData->isNotEmpty()) ||
                    ($item->journalData && $item->journalData->isNotEmpty());

                return [
                    'id' => $item->id,
                    'project_id' => $item->project_id,
                    'hierarchy_level' => $item->hierarchy_level,
                    'process_status' => $item->process_status,
                    'created_by' => $item->created_by,
                    'has_role' => $hasAnyRole ? true : false,
                ];
            });
        }

        function getEntryProcessCount($statuses, $currentYear, $pid = null)
        {
            $query = EntryProcessModel::select('id', 'hierarchy_level', 'project_id', 'process_status')
                ->with(['userData'])

                ->whereIn('process_status', $statuses)
                // ->whereYear('entry_date', $currentYear)
                // ->whereRaw("DATE_FORMAT(entry_date, '%Y-%m') = ?", [$selectedMonth])
                ->whereNotIn('process_status', ['completed', 'client_review', 'pending_author'])
                ->where('is_deleted', 0);

            if ($pid !== null) {
                $query->where('created_by', $pid);
            }

            // Return the count of matching rows
            return $query->count();
        }

        // In progress
        $statusesInProgress = ['in_progress'];
        $inProgress = getEntryProcessData($statusesInProgress, $currentYear);
        $inProgressCount = getEntryProcessCount($statusesInProgress, $currentYear);

        // To-do list
        $statusesToDo = ['to_do', 'not_assigned'];
        $tasks = getEntryProcessData($statusesToDo, $currentYear);
        $to_docount = getEntryProcessCount($statusesToDo, $currentYear);

        // In work list
        $inWorksStatuses = ['pending_author'];
        $inWorks = getEntryProcessData($inWorksStatuses, $currentYear);
        $inWorksCount = getEntryProcessCount($inWorksStatuses, $currentYear);

        // Review list
        $reviewsStatuses = ['withdrawal'];
        $reviews = getEntryProcessData($reviewsStatuses, $currentYear);
        $reviewerCount = getEntryProcessCount($reviewsStatuses, $currentYear);

        // Completed list
        $completedStatuses = ['completed'];
        $completed = getEntryProcessData($completedStatuses, $currentYear);
        $completedCount = getEntryProcessCount($completedStatuses, $currentYear);

        // Correction list
        $correctionsStatuses = ['in_progress'];
        $corrections = getEntryProcessData($correctionsStatuses, $currentYear);
        $correctionsCount = getEntryProcessCount($correctionsStatuses, $currentYear);

        $notAssignedProjects = EntryProcessModel::where('process_status', 'not_assigned')
            ->select('id', 'project_id', 'type_of_work', 'process_status', 'hierarchy_level', 'created_at')
            ->where('is_deleted', 0)

            ->orderBy('updated_at', 'desc')
            ->get();

        $urgentDataList = EntryProcessModel::select('id', 'journal', 'writer', 'statistican', 'reviewer', 'hierarchy_level', 'project_id')->where('hierarchy_level', 'urgent_important')
            ->where('is_deleted', 0)
            ->whereNotIn('process_status', ['completed', 'client_review', 'pending_author'])
            // ->whereRaw("DATE_FORMAT(entry_date, '%Y-%m') = ?", [$selectedMonth])
            ->orderBy('id', 'desc')
            ->get();

        // Count the urgent data
        $urgentDataListCount = $urgentDataList->count();

        $currentDate = now()->format('Y-m-d');
        $projectdelayDataList = EntryProcessModel::with(['paymentProcess', 'instituteInfo', 'departmentInfo', 'professionInfo'])->select(
            'id',
            'title',
            'institute',
            'department',
            'entry_date',
            'profession',
            'client_name',
            // DB::raw("CONCAT(DATEDIFF(projectduration, created_at), ' days ', MOD(TIMESTAMPDIFF(HOUR, created_at, projectduration), 24), ' hrs') AS projectduration"),
            DB::raw("CONCAT(DATEDIFF(projectduration, entry_date), ' days') AS projectduration"),
            'hierarchy_level',
            'project_id'
        )
            ->where('projectduration', '<', $currentDate)
            ->where('is_deleted', 0)
            ->whereNotIn('process_status', ['completed', 'client_review', 'pending_author'])
            // ->whereRaw("DATE_FORMAT(entry_date, '%Y-%m') = ?", [$selectedMonth])
            ->orderBy('id', 'desc')
            ->get();

        $projectdelayDataCount = $projectdelayDataList->count();
        $peopleIds_pm = People::where('position', '28')
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();

        $journalEntries = ProjectAssignDetails::select('status', 'id', 'project_id')->with(['projectData'])
            ->where('type', 'publication_manager')
            ->whereIn('created_by', $peopleIds_pm)
            ->whereIn('status', array_keys($journalStatusCounts))
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })
            ->get();

        $journalStatusIds = [
            'submit_to_journal' => [],
            'reviewer_comments' => [],
            'submitted' => [],
            'peer_review' => [],
            'resubmission' => [],
            'rejected' => [],
        ];

        foreach ($journalEntries as $entry) {
            if (isset($journalStatusCounts[$entry->status])) {
                $journalStatusCounts[$entry->status]++;

                $journalStatusIds[$entry->status][] = $entry->project_id;
            }
        }

        $submitted_peer_review_count = $journalStatusCounts['submitted'] + $journalStatusCounts['peer_review'];
        $submitted_peer_review_ids = array_merge($journalStatusIds['submitted'], $journalStatusIds['peer_review']);

        $resubmission_rejected_count = $journalStatusCounts['resubmission'] + $journalStatusCounts['rejected'];
        $resubmission_rejected_ids = array_merge($journalStatusIds['resubmission'], $journalStatusIds['rejected']);

        $result = [
            'submit_to_journal' => [
                'count' => $journalStatusCounts['submit_to_journal'],
                'ids' => $journalStatusIds['submit_to_journal'],
            ],
            'reviewer_comments' => [
                'count' => $journalStatusCounts['reviewer_comments'],
                'ids' => $journalStatusIds['reviewer_comments'],
            ],
            'submitted_peer_review' => [
                'count' => $submitted_peer_review_count,
                'ids' => $submitted_peer_review_ids,
            ],
            'resubmission_rejected' => [
                'count' => $resubmission_rejected_count,
                'ids' => $resubmission_rejected_ids,
            ],
        ];
        //freelancer
        $typeOfWorkCounts = $typeOfWorkCounts ?? [];
        $processStatusCounts = $processStatusCounts ?? [];
        $journalStatusCounts = $journalStatusCounts ?? [];
        $completedCounts = $completedCounts ?? [];
        $urgentImportantCount = 0;
        $importantNotUrgentCount = 0;
        $urgentNotImportantCount = 0;
        $notUrgentNotImportantCount = 0;
        $notAssignedCount = 0;
        $projectDelayCount = 0;
        $writerCount = 0;
        $reviewerCount = 0;
        $writerStatusCounts = $writerStatusCounts ?? [];
        $reviewerStatusCounts = $reviewerStatusCounts ?? [];
        $freelancerPaymentCount = 0;
        $freelancers = [];
        $paymentStatusCounts = $paymentStatusCounts ?? [];

        $idsToCheck = [];
        $projectIds = $entries->pluck('id')->toArray();
        // Count writer and reviewer
        $writerProjectCount = ProjectAssignDetails::with(['projectData'])->whereIn('project_id', $projectIds)->where('type', 'writer')
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })
            ->count();

        $reviewerProjectCount = ProjectAssignDetails::with(['projectData'])->whereIn('project_id', $projectIds)->where('type', 'reviewer')
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })
            ->count();

        $submitted_peer = ProjectAssignDetails::with(['projectData'])->whereIn('status', ['submit_to_journal', 'peer_review'])
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);

            })
            ->count();
        $resubmission_rejected = ProjectAssignDetails::with(['projectData'])->whereIn('status', ['resubmission', 'rejected'])
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })
            ->count();

        $journalEntriesCount = ProjectAssignDetails::with(['projectData'])->whereIn('status', [
            'submit_to_journal',
            'peer_review',
            'reviewer_comments',
        ])
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })
            ->count();

        foreach ($entries as $entry) {
            // Count type_of_work
            $typeOfWorkCounts[$entry->type_of_work] = ($typeOfWorkCounts[$entry->type_of_work] ?? 0) + 1;

            // Count process_status
            $processStatusCounts[$entry->process_status] = ($processStatusCounts[$entry->process_status] ?? 0) + 1;

            // if (isset($entry->journal_status)) {
            //     if ($entry->journal_status === 'submitted' || $entry->journal_status === 'peer_review') {
            //         $journalStatusCounts['submit_peer_review'] = ($journalStatusCounts['submit_peer_review'] ?? 0) + 1;
            //     } elseif ($entry->journal_status === 'resubmission' || $entry->journal_status === 'rejected') {
            //         $journalStatusCounts['resubmission_rejected'] = ($journalStatusCounts['resubmission_rejected'] ?? 0) + 1;
            //     } else {
            //         $journalStatusCounts[$entry->journal_status] = ($journalStatusCounts[$entry->journal_status] ?? 0) + 1;
            //     }
            // }

            // Count journal_status use $journalStatusCounts
            $journalStatusCounts[$entry->process_status] = ($journalStatusCounts[$entry->process_status] ?? 0) + 1;

            if ($entry->process_status === 'completed') {
                $completedCounts[$entry->type_of_work] = ($completedCounts[$entry->type_of_work] ?? 0) + 1;
            }

            if ($entry->hierarchy_level === 'urgent_important' && $entry->process_status !== 'completed') {
                $urgentImportantCount++;
            }

            if ($entry->hierarchy_level === 'important_not_urgent') {
                $importantNotUrgentCount++;
            }

            if ($entry->hierarchy_level === 'urgent_not_important') {
                $urgentNotImportantCount++;
            }

            if ($entry->hierarchy_level === 'not_urgent_not_important') {
                $notUrgentNotImportantCount++;
            }

            if ($entry->process_status === 'not_assigned' && $entry->process_status !== 'completed') {
                $notAssignedCount++;
            }
            $delayedProjects = [];

            $projectstatus = ProjectViewStatus::with(['projectViews'])->where('project_id', $entry->id)->Where('project_status', '!=', 'completed')
                ->whereHas('projectViews', function ($query) use ($fromDate, $toDate) {
                    $query->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate);
                })
                ->orderBy('id', 'desc')->latest()->first();

            $projectstatus_completeddate = $projectstatus ? $projectstatus->created_date : null;

            $projectDurationDate = $entry->projectduration;

            if ($projectstatus_completeddate) {

                if ($projectDurationDate < $currentDate) {
                    $projectDelayCount++;

                    $delayedProjects[] = [
                        'project_id' => $entry->project_id,
                        'id' => $entry->id,
                        'entry_date' => $entry->entry_date,
                        'hierarchy_level' => $entry->hierarchy_level,
                        'type_of_work' => $entry->type_of_work,
                        'title' => $entry->title,
                        'process_status' => $entry->process_status,
                        'writer' => $entry->writer,
                        'reviewer' => $entry->reviewer,
                        'statistican' => $entry->statistican,
                        'journal' => $entry->journal,
                        'writer_status' => $entry->writer_status,
                        'reviewer_status' => $entry->reviewer_status,
                        'statistican_status' => $entry->statistican_status,
                        'journal_status' => $entry->journal_status,
                        'client_name' => $entry->client_name,

                        'project_duration' => $entry->projectduration,
                    ];
                } else {
                    if ($projectDurationDate < $currentDate) {
                        $delayedProjects[] = [
                            'project_id' => $entry->project_id,
                            'id' => $entry->id,
                            'entry_date' => $entry->entry_date,
                            'hierarchy_level' => $entry->hierarchy_level,
                            'type_of_work' => $entry->type_of_work,
                            'title' => $entry->title,
                            'process_status' => $entry->process_status,
                            'writer' => $entry->writer,
                            'reviewer' => $entry->reviewer,
                            'statistican' => $entry->statistican,
                            'journal' => $entry->journal,
                            'writer_status' => $entry->writer_status,
                            'reviewer_status' => $entry->reviewer_status,
                            'statistican_status' => $entry->statistican_status,
                            'journal_status' => $entry->journal_status,
                            'client_name' => $entry->client_name,

                            'project_duration' => $entry->projectduration,
                        ];
                    }
                }
            }
            if ($entry->type_of_work === 'manuscript') {
                $assignProject = ProjectAssignDetails::select('status', 'type')->with('projectData')->where('project_id', $entry->id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->get();

                foreach ($assignProject as $project) {
                    if ($project->type === 'writer') {
                        $writerStatusCounts[$project->status] = ($writerStatusCounts[$project->status] ?? 0) + 1;
                    }

                    if ($project->type === 'reviewer') {
                        $reviewerStatusCounts[$project->status] = ($reviewerStatusCounts[$project->status] ?? 0) + 1;
                    }
                }
            }

            //freeelancer count
            $assignproject = ProjectAssignDetails::with('projectData')->where('project_id', $entry->id)->pluck('assign_user')
                //     ->whereHas('projectData', function ($query) use ($selectedMonth) {
                //     $query    ->whereRaw("DATE_FORMAT(entry_date, '%Y-%m') = ?", [$selectedMonth]);
                // })
                ->toArray();

            if (! empty($assignproject)) {
                $assignprojectIds[$entry->id] = array_unique($assignproject);
            }
        }
        // $allAssignUserIds = array_unique(array_merge(...array_values($assignprojectIds)));
        // $userhrms = DB::connection('mysql_medics_hrms')
        //     ->table('employee_details')
        //     ->where('employee_type', 'freelancers')
        //     // ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
        //     ->whereIn('id', $allAssignUserIds)
        //     ->where('status', '1')
        //     ->get();

        // $freelancersById = $userhrms->keyBy('id');
        // foreach ($entries as $entry) {
        //     if (! isset($assignprojectIds[$entry->id])) {
        //         continue;
        //     }

        //     $addedProjectIds = []; // To track unique project IDs

        //     foreach ($assignprojectIds[$entry->id] as $freelancerId) {
        //         if (isset($freelancersById[$freelancerId])) {
        //             $user = $freelancersById[$freelancerId];
        //             // Check if project_id already added
        //             if (! in_array($entry->project_id, $addedProjectIds)) {
        //                 $freelancerPaymentCount++;
        //                 $freelancers[] = [
        //                     // 'id' => $user->id,
        //                     // 'name' => $user->employee_name,
        //                     // 'employee_type' => $user->employee_type,
        //                     // 'email' => $user->email_address,
        //                     'id' => $entry->id,
        //                     'project_id' => $entry->project_id,
        //                     // 'entry_date' => $entry->entry_date,
        //                     'hierarchy_level' => $entry->hierarchy_level,
        //                     'type_of_work' => $entry->type_of_work,
        //                 ];

        //                 $addedProjectIds[] = $entry->project_id;
        //             }
        //         }
        //     }
        // }
        $assignprojectIds = $assignprojectIds ?? [];

        if (! empty($assignprojectIds)) {
            $allAssignUserIds = array_unique(array_merge(...array_values($assignprojectIds)));

            if (! empty($allAssignUserIds)) {
                $userhrms = DB::connection('mysql_medics_hrms')
                    ->table('employee_details')
                    ->where('employee_type', 'freelancers')
                    ->whereIn('id', $allAssignUserIds)
                    ->where('status', '1')
                    ->get();

                $freelancersById = $userhrms->keyBy('id');

                foreach ($entries as $entry) {
                    if (! isset($assignprojectIds[$entry->id])) {
                        continue;
                    }

                    $addedProjectIds = [];

                    foreach ($assignprojectIds[$entry->id] as $freelancerId) {
                        if (isset($freelancersById[$freelancerId])) {
                            if (! in_array($entry->project_id, $addedProjectIds)) {
                                $freelancerPaymentCount++;
                                $freelancers[] = [
                                    'id' => $entry->id,
                                    'project_id' => $entry->project_id,
                                    'hierarchy_level' => $entry->hierarchy_level,
                                    'type_of_work' => $entry->type_of_work,
                                ];

                                $addedProjectIds[] = $entry->project_id;
                            }
                        }
                    }
                }
            }
        }

        foreach ($paymentEntries as $pentry) {
            if (isset($pentry->payment_status)) {
                if (! isset($paymentStatusCounts[$pentry->payment_status])) {
                    $paymentStatusCounts[$pentry->payment_status] = 0;
                }

                $paymentStatusCounts[$pentry->payment_status]++;
            }
        }

        return response()->json([
            'inToDoList' => $notAssignedProjects,
            'inProgress' => $inProgress,
            'inProgressCount' => $inProgressCount,
            'urgentDataList' => $urgentDataList,
            'urgentDataListCount' => $urgentDataListCount,
            'projectdelayDataList' => $projectdelayDataList,
            'projectdelayDataCount' => $projectdelayDataCount,
            'freelancerProjecctList' => $freelancers,
            'projectStatusList' => $projectStatusList,
            'projectStatusCount' => $projectStatusCount,

            'total_count' => $totalCount,
            'typeofwork' => $typeOfWorkCounts,
            'process_staus' => $processStatusCounts,
            'journal_status' => $result,
            'completedCount' => $completedCounts,
            'paymentStatusCounts' => $paymentStatusCounts,
            'not_assigned' => $notAssignedCount,
            'freelancet_payment_count' => $freelancerPaymentCount,
            'inhouseExternal' => $this->inhouseExternal($request, $fromDate, $toDate)->getData(true),
            'monthWiseTable' => $this->monthWiseTable($position, $fromDate, $toDate),

        ]);
    }

    // admin Dashboard
    public function adminDashboard(Request $request)
    {
        $currentYear = date('Y');
        // $selectedMonth = $request->input('month'); // format: YYYY-MM
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        // ->whereRaw("DATE_FORMAT(entry_date, '%Y-%m') = ?", [$selectedMonth])
        $position = $request->get('position');

        $entries = EntryProcessModel::select('id', 'type_of_work', 'project_id', 'process_status', 'hierarchy_level', 'projectduration', 'created_by')->where('is_deleted', 0)->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)->get();
        $totalCount = EntryProcessModel::select('id')->where('is_deleted', 0)->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)->count();

        $projectStatusList = EntryProcessModel::with('projectStatus') // Just eager-load the relation
            ->whereHas('projectStatus', function ($query) {
                $query->where('status', 'rejected')
                    ->orderBy('created_at', 'desc');
            })
            ->where('is_deleted', 0)
            ->where('process_status', '!=', 'completed')
            // ->whereYear('entry_date', $currentYear)
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->orderBy('created_at', 'desc')
            ->get();

        $projectStatusCount = $projectStatusList->count();

        // Initialize counters
        $typeOfWorkCounts = [
            'manuscript' => 0,
            'thesis' => 0,
            'statistics' => 0,
            'presentation' => 0,
            'others' => 0,
        ];
        $processStatusCounts = [
            'not_assigned' => 0,
            'pending_author' => 0,
            'withdrawal' => 0,
            'in_progress' => 0,
            'completed' => 0,
        ];
        $journalStatusCounts = [
            'submit_to_journal' => 0,
            'submitted' => 0,
            'peer_review' => 0,
            'reviewer_comments' => 0,
            'resubmission' => 0,
            'rejected' => 0,
        ];
        $completedCounts = $typeOfWorkCounts;
        $urgentImportantCount = 0;
        $notAssignedCount = 0;
        $projectDelayCount = 0;
        $freelancerPaymentCount = 0;
        $writerProjectCount = 0;
        $reviewerProjectCount = 0;

        $writerStatusCounts = [
            'completed' => 0,
            'on_going' => 0,
            'correction' => 0,
            'plag_correction' => 0,
        ];

        $reviewerStatusCounts = [
            'completed' => 0,
            'on_going' => 0,
            'correction' => 0,
            'plag_correction' => 0,
        ];

        $paymentStatusCounts = [
            'advance_pending' => 0,
            'partial_payment_pending' => 0,
            'final_payment_pending' => 0,
            'completed' => 0,
        ];

        $paymentEntries = PaymentStatusModel::select('payment_status', 'id')
            ->with(['projectData'])
            ->whereIn('payment_status', [
                'advance_pending',
                'partial_payment_pending',
                'final_payment_pending',
                'completed',
            ])
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->where('is_deleted', 0)
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })

            ->get();

        function getEntryProcessData($statuses, $currentYear, $pid, $fromDate, $toDate)
        {
            $query = EntryProcessModel::select('id', 'hierarchy_level', 'project_id', 'process_status', 'created_by')
                ->with([
                    'userData',
                    'writerData',
                    'reviewerData',
                    'statisticanData',
                    'journalData',
                ])
                ->whereIn('process_status', $statuses)
                // ->whereYear('entry_date', $currentYear)
                ->whereDate('entry_date', '>=', $fromDate)
                ->whereDate('entry_date', '<=', $toDate)
                ->where('is_deleted', 0)
                ->where('process_status', '!=', 'completed');

            // Apply the created_by filter if $pid is provided
            if ($pid !== null) {
                $query = $query->where('created_by', $pid);
            }

            return $query->orderBy('id', 'desc')->get()->map(function ($item) {
                $hasAnyRole =
                    ($item->writerData && $item->writerData->isNotEmpty()) ||
                    ($item->reviewerData && $item->reviewerData->isNotEmpty()) ||
                    ($item->statisticanData && $item->statisticanData->isNotEmpty()) ||
                    ($item->journalData && $item->journalData->isNotEmpty());

                return [
                    'id' => $item->id,
                    'project_id' => $item->project_id,
                    'hierarchy_level' => $item->hierarchy_level,
                    'process_status' => $item->process_status,
                    'created_by' => $item->created_by,
                    'has_role' => $hasAnyRole ? true : false,
                ];
            });
        }

        function getEntryProcessCount($statuses, $currentYear, $pid = null)
        {
            $query = EntryProcessModel::select('id', 'hierarchy_level', 'project_id', 'process_status')
                ->with(['userData'])

                ->whereIn('process_status', $statuses)
                // ->whereYear('entry_date', $currentYear)
                ->whereDate('entry_date', '>=', $fromDate)
                ->whereDate('entry_date', '<=', $toDate)
                ->where('process_status', '!=', 'completed')
                ->where('is_deleted', 0);

            if ($pid !== null) {
                $query->where('created_by', $pid);
            }

            // Return the count of matching rows
            return $query->count();
        }

        $urgentDataList = EntryProcessModel::select('id', 'journal', 'writer', 'statistican', 'reviewer', 'hierarchy_level', 'project_id')->where('hierarchy_level', 'urgent_important')
            ->where('is_deleted', 0)
            ->where('process_status', '!=', 'completed')
            // ->whereYear('entry_date', $currentYear)
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->orderBy('id', 'desc')
            ->get();

        // Count the urgent data
        $urgentDataListCount = $urgentDataList->count();

        $currentDate = now()->format('Y-m-d');
        $projectdelayDataList = EntryProcessModel::with(['paymentProcess', 'instituteInfo', 'departmentInfo', 'professionInfo'])->select(
            'id',
            'title',
            'institute',
            'department',
            'entry_date',
            'profession',
            'client_name',
            // DB::raw("CONCAT(DATEDIFF(projectduration, created_at), ' days ', MOD(TIMESTAMPDIFF(HOUR, created_at, projectduration), 24), ' hrs') AS projectduration"),
            DB::raw("CONCAT(DATEDIFF(projectduration, entry_date), ' days') AS projectduration"),
            'hierarchy_level',
            'project_id'
        )
            ->where('projectduration', '<', $currentDate)
            ->where('is_deleted', 0)
            ->where('process_status', '!=', 'completed')
            // ->whereYear('entry_date', $currentYear)
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->orderBy('id', 'desc')
            ->get();

        $projectdelayDataCount = $projectdelayDataList->count();
        $peopleIds_pm = People::where('position', '28')
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();

        $journalEntries = ProjectAssignDetails::select('status', 'id', 'project_id')
            ->with(['projectData'])
            ->where('type', 'publication_manager')
            // ->whereIn('created_by', $peopleIds_pm)
            ->whereIn('status', array_keys($journalStatusCounts))
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->where('is_deleted', 0)
                    ->where('process_status', '!=', 'completed')
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })
            ->get();

        $journalStatusIds = [
            'submit_to_journal' => [],
            'reviewer_comments' => [],
            'submitted' => [],
            'peer_review' => [],
            'resubmission' => [],
            'rejected' => [],
        ];

        foreach ($journalEntries as $entry) {
            if (isset($journalStatusCounts[$entry->status])) {
                $journalStatusCounts[$entry->status]++;

                $journalStatusIds[$entry->status][] = $entry->project_id;
            }
        }

        $submitted_peer_review_count = $journalStatusCounts['submitted'] + $journalStatusCounts['peer_review'];
        $submitted_peer_review_ids = array_merge($journalStatusIds['submitted'], $journalStatusIds['peer_review']);

        $resubmission_rejected_count = $journalStatusCounts['resubmission'] + $journalStatusCounts['rejected'];
        $resubmission_rejected_ids = array_merge($journalStatusIds['resubmission'], $journalStatusIds['rejected']);

        $result = [
            'submit_to_journal' => [
                'count' => $journalStatusCounts['submit_to_journal'],
                'ids' => $journalStatusIds['submit_to_journal'],
            ],
            'reviewer_comments' => [
                'count' => $journalStatusCounts['reviewer_comments'],
                'ids' => $journalStatusIds['reviewer_comments'],
            ],
            'submitted_peer_review' => [
                'count' => $submitted_peer_review_count,
                'ids' => $submitted_peer_review_ids,
            ],
            'resubmission_rejected' => [
                'count' => $resubmission_rejected_count,
                'ids' => $resubmission_rejected_ids,
            ],
        ];
        //freelancer
        $typeOfWorkCounts = $typeOfWorkCounts ?? [];
        $processStatusCounts = $processStatusCounts ?? [];
        $journalStatusCounts = $journalStatusCounts ?? [];
        $completedCounts = $completedCounts ?? [];
        $urgentImportantCount = 0;
        $importantNotUrgentCount = 0;
        $urgentNotImportantCount = 0;
        $notUrgentNotImportantCount = 0;
        $notAssignedCount = 0;
        $projectDelayCount = 0;
        $writerCount = 0;
        $reviewerCount = 0;
        $writerStatusCounts = $writerStatusCounts ?? [];
        $reviewerStatusCounts = $reviewerStatusCounts ?? [];
        $freelancerPaymentCount = 0;
        $freelancers = [];
        $paymentStatusCounts = $paymentStatusCounts ?? [];

        $idsToCheck = [];
        $projectIds = $entries->pluck('id')->toArray();
        // Count writer and reviewer
        $writerProjectCount = ProjectAssignDetails::with(['projectData'])
            ->whereIn('project_id', $projectIds)->where('type', 'writer')
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->where('is_deleted', 0)
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })->count();

        $reviewerProjectCount = ProjectAssignDetails::with(['projectData'])->whereIn('project_id', $projectIds)->where('type', 'reviewer')
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->where('is_deleted', 0)
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })
            ->count();

        $submitted_peer = ProjectAssignDetails::with(['projectData'])->whereIn('status', ['submit_to_journal', 'peer_review'])
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->where('is_deleted', 0)
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })
            ->count();
        $resubmission_rejected = ProjectAssignDetails::with(['projectData'])->whereIn('status', ['resubmission', 'rejected'])
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->where('is_deleted', 0)
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })->count();

        $journalEntriesCount = ProjectAssignDetails::with(['projectData'])->whereIn('status', [
            'submit_to_journal',
            'peer_review',
            'reviewer_comments',
        ])->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
            $query->where('is_deleted', 0)
                ->whereDate('entry_date', '>=', $fromDate)
                ->whereDate('entry_date', '<=', $toDate);
        })->count();

        foreach ($entries as $entry) {
            // Count type_of_work
            $typeOfWorkCounts[$entry->type_of_work] = ($typeOfWorkCounts[$entry->type_of_work] ?? 0) + 1;

            // Count process_status
            $processStatusCounts[$entry->process_status] = ($processStatusCounts[$entry->process_status] ?? 0) + 1;

            // if (isset($entry->journal_status)) {
            //     if ($entry->journal_status === 'submitted' || $entry->journal_status === 'peer_review') {
            //         $journalStatusCounts['submit_peer_review'] = ($journalStatusCounts['submit_peer_review'] ?? 0) + 1;
            //     } elseif ($entry->journal_status === 'resubmission' || $entry->journal_status === 'rejected') {
            //         $journalStatusCounts['resubmission_rejected'] = ($journalStatusCounts['resubmission_rejected'] ?? 0) + 1;
            //     } else {
            //         $journalStatusCounts[$entry->journal_status] = ($journalStatusCounts[$entry->journal_status] ?? 0) + 1;
            //     }
            // }

            // Count journal_status use $journalStatusCounts
            $journalStatusCounts[$entry->process_status] = ($journalStatusCounts[$entry->process_status] ?? 0) + 1;

            if ($entry->process_status === 'completed') {
                $completedCounts[$entry->type_of_work] = ($completedCounts[$entry->type_of_work] ?? 0) + 1;
            }

            if ($entry->hierarchy_level === 'urgent_important' && $entry->process_status !== 'completed') {
                $urgentImportantCount++;
            }

            if ($entry->hierarchy_level === 'important_not_urgent') {
                $importantNotUrgentCount++;
            }

            if ($entry->hierarchy_level === 'urgent_not_important') {
                $urgentNotImportantCount++;
            }

            if ($entry->hierarchy_level === 'not_urgent_not_important') {
                $notUrgentNotImportantCount++;
            }

            if ($entry->process_status === 'not_assigned' && $entry->process_status !== 'completed') {
                $notAssignedCount++;
            }
            $delayedProjects = [];

            $projectstatus = ProjectViewStatus::with(['projectViews'])->where('project_id', $entry->id)->Where('project_status', '!=', 'completed')
                ->whereHas('projectViews', function ($query) use ($fromDate, $toDate) {
                    $query->where('is_deleted', 0)
                        ->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate);
                })
                ->orderBy('id', 'desc')->latest()->first();

            $projectstatus_completeddate = $projectstatus ? $projectstatus->created_date : null;

            $projectDurationDate = $entry->projectduration;

            if ($projectstatus_completeddate) {

                if ($projectDurationDate < $currentDate) {
                    $projectDelayCount++;

                    $delayedProjects[] = [
                        'project_id' => $entry->project_id,
                        'id' => $entry->id,
                        'entry_date' => $entry->entry_date,
                        'hierarchy_level' => $entry->hierarchy_level,
                        'type_of_work' => $entry->type_of_work,
                        'title' => $entry->title,
                        'process_status' => $entry->process_status,
                        'writer' => $entry->writer,
                        'reviewer' => $entry->reviewer,
                        'statistican' => $entry->statistican,
                        'journal' => $entry->journal,
                        'writer_status' => $entry->writer_status,
                        'reviewer_status' => $entry->reviewer_status,
                        'statistican_status' => $entry->statistican_status,
                        'journal_status' => $entry->journal_status,
                        'client_name' => $entry->client_name,

                        'project_duration' => $entry->projectduration,
                    ];
                } else {
                    if ($projectDurationDate < $currentDate) {
                        $delayedProjects[] = [
                            'project_id' => $entry->project_id,
                            'id' => $entry->id,
                            'entry_date' => $entry->entry_date,
                            'hierarchy_level' => $entry->hierarchy_level,
                            'type_of_work' => $entry->type_of_work,
                            'title' => $entry->title,
                            'process_status' => $entry->process_status,
                            'writer' => $entry->writer,
                            'reviewer' => $entry->reviewer,
                            'statistican' => $entry->statistican,
                            'journal' => $entry->journal,
                            'writer_status' => $entry->writer_status,
                            'reviewer_status' => $entry->reviewer_status,
                            'statistican_status' => $entry->statistican_status,
                            'journal_status' => $entry->journal_status,
                            'client_name' => $entry->client_name,

                            'project_duration' => $entry->projectduration,
                        ];
                    }
                }
            }
            if ($entry->type_of_work === 'manuscript') {
                $assignProject = ProjectAssignDetails::select('status', 'type')->with('projectData')->where('project_id', $entry->id)
                    ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                        $query->where('is_deleted', 0)
                            ->whereDate('entry_date', '>=', $fromDate)
                            ->whereDate('entry_date', '<=', $toDate);
                    })
                    ->get();

                foreach ($assignProject as $project) {
                    if ($project->type === 'writer') {
                        $writerStatusCounts[$project->status] = ($writerStatusCounts[$project->status] ?? 0) + 1;
                    }

                    if ($project->type === 'reviewer') {
                        $reviewerStatusCounts[$project->status] = ($reviewerStatusCounts[$project->status] ?? 0) + 1;
                    }
                }
            }

            //freeelancer count
            $assignproject = ProjectAssignDetails::with('projectData')->where('project_id', $entry->id)
                ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                    $query->where('is_deleted', 0)
                        ->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate);
                })
                ->pluck('assign_user')->toArray();

            if (! empty($assignproject)) {
                $assignprojectIds[$entry->id] = array_unique($assignproject);
            }
        }
        // $allAssignUserIds = array_unique(array_merge(...array_values($assignprojectIds)));
        // if (! empty($allAssignUserIds)) {
        //     $userhrms = DB::connection('mysql_medics_hrms')
        //         ->table('employee_details')
        //         ->where('employee_type', 'freelancers')
        //            // ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
        //         ->whereIn('id', $allAssignUserIds)
        //         ->where('status', '1')
        //         ->get();

        //     $freelancersById = $userhrms->keyBy('id');
        //     foreach ($entries as $entry) {
        //         if (! isset($assignprojectIds[$entry->id])) {
        //             continue;
        //         }

        //         $addedProjectIds = []; // To track unique project IDs

        //         foreach ($assignprojectIds[$entry->id] as $freelancerId) {
        //             if (isset($freelancersById[$freelancerId])) {
        //                 $user = $freelancersById[$freelancerId];
        //                 // Check if project_id already added
        //                 if (! in_array($entry->project_id, $addedProjectIds)) {
        //                     $freelancerPaymentCount++;
        //                     $freelancers[] = [
        //                         // 'id' => $user->id,
        //                         // 'name' => $user->employee_name,
        //                         // 'employee_type' => $user->employee_type,
        //                         // 'email' => $user->email_address,
        //                         'id' => $entry->id,
        //                         'project_id' => $entry->project_id,
        //                         // 'entry_date' => $entry->entry_date,
        //                         'hierarchy_level' => $entry->hierarchy_level,
        //                         'type_of_work' => $entry->type_of_work,
        //                     ];

        //                     $addedProjectIds[] = $entry->project_id;
        //                 }
        //             }
        //         }
        //     }
        // }

        $assignprojectIds = $assignprojectIds ?? [];

        if (! empty($assignprojectIds)) {
            $allAssignUserIds = array_unique(array_merge(...array_values($assignprojectIds)));

            if (! empty($allAssignUserIds)) {
                $userhrms = DB::connection('mysql_medics_hrms')
                    ->table('employee_details')
                    ->where('employee_type', 'freelancers')
                    ->whereIn('id', $allAssignUserIds)
                    ->where('status', '1')
                    ->get();

                $freelancersById = $userhrms->keyBy('id');

                foreach ($entries as $entry) {
                    if (! isset($assignprojectIds[$entry->id])) {
                        continue;
                    }

                    $addedProjectIds = [];

                    foreach ($assignprojectIds[$entry->id] as $freelancerId) {
                        if (isset($freelancersById[$freelancerId])) {
                            if (! in_array($entry->project_id, $addedProjectIds)) {
                                $freelancerPaymentCount++;
                                $freelancers[] = [
                                    'id' => $entry->id,
                                    'project_id' => $entry->project_id,
                                    'hierarchy_level' => $entry->hierarchy_level,
                                    'type_of_work' => $entry->type_of_work,
                                ];

                                $addedProjectIds[] = $entry->project_id;
                            }
                        }
                    }
                }
            }
        }

        foreach ($paymentEntries as $pentry) {
            if (isset($pentry->payment_status)) {
                if (! isset($paymentStatusCounts[$pentry->payment_status])) {
                    $paymentStatusCounts[$pentry->payment_status] = 0;
                }

                $paymentStatusCounts[$pentry->payment_status]++;
            }
        }

        return response()->json([

            // 'urgentDataList' => $urgentDataList,
            'urgentDataListCount' => $urgentDataListCount,
            //    'projectdelayDataList' => $projectdelayDataList,
            'projectdelayDataCount' => $projectdelayDataCount,
            //'freelancerProjecctList' => $freelancers,
            //    'projectStatusList' => $projectStatusList,
            'projectStatusCount' => $projectStatusCount,

            'total_count' => $totalCount,
            'typeofwork' => $typeOfWorkCounts,
            'process_staus' => $processStatusCounts,
            'journal_status' => $result,
            'completedCount' => $completedCounts,
            'paymentStatusCounts' => $paymentStatusCounts,
            'not_assigned' => $notAssignedCount,
            'freelancet_payment_count' => $freelancerPaymentCount,
            'inhouseExternal' => $this->inhouseExternal($request, $fromDate, $toDate)->getData(true),
            'monthWiseTable' => $this->monthWiseTable($position, $fromDate, $toDate),
            // 'monthWiseTable' => $this->monthWiseTable($position, $selectedMonth),

        ]);
    }

    public function dashboardProjectList(Request $request)
    {
        $position = $request->get('position');
        // dd($position);
        // Log::info("position",$position);
        // console.log($position);

        if ($position === 'admin') {
            return [
                'inhouseExternal' => $this->inhouseExternal($request)->getData(true),
                'monthWiseTable' => $this->monthWiseTable($position),
                'data' => $this->adminDashboard($request)->getData(true),
            ];
        } elseif ($position === 'team_coordinator') {
            return [
                'inhouseExternal' => $this->inhouseExternal($request)->getData(true),
                'monthWiseTable' => $this->monthWiseTable($position),
                'data' => $this->tcDashboard($request)->getData(true),
            ];
        } elseif ($position === 'project_manager') {
            return [
                'inhouseExternal' => $this->inhouseExternal($request)->getData(true),
                'monthWiseTable' => $this->monthWiseTable($position),
                'data' => $this->pmDashboard($request)->getData(true),
            ];
        }

        return ['error' => 'Invalid position'];
    }

    public function dashboardWRList(Request $request)
    {
        $position = $request->position;

        $created_by = $request->created_by;
        $positions = explode(',', $position);

        $urgentImportantCount = 0;
        $importantNotUrgentCount = 0;
        $urgentNotImportantCount = 0;
        $notUrgentNotImportantCount = 0;
        $notAssignedCount = 0;
        $notAssignedCount = 0;
        $writerCount = 0;
        $reviewerCount = 0;
        $statisticanCount = 0;

        $correctionStatuses = ['correction'];

        $manuscriptData = [
            'completed' => ['writer' => 0, 'reviewing' => 0],
            'plag_correction' => ['writer' => 0, 'reviewing' => 0],
            'correction' => ['writer' => 0, 'reviewing' => 0],
            'ongoing' => ['writer' => 0, 'reviewing' => 0],
        ];
        $thesisData = [
            'completed' => ['writer' => 0, 'reviewing' => 0],
            'plag_correction' => ['writer' => 0, 'reviewing' => 0],
            'correction' => ['writer' => 0, 'reviewing' => 0],
            'ongoing' => ['writer' => 0, 'reviewing' => 0],
        ];
        $manuscriptData1 = [
            'completed' => ['statistican' => 0],
            'client_review' => ['statistican' => 0],
            'correction' => ['statistican' => 0],
            'on_going' => ['statistican' => 0],
            'query' => ['statistican' => 0],
        ];

        // Query Base
        $query = EntryProcessModel::with(['writerData', 'reviewerData', 'statisticanData'])
            ->where('is_deleted', 0)
            ->orderBy('created_at', 'desc')
            ->select(
                'id',
                'entry_date',
                'title',
                'project_id',
                'type_of_work',
                'email',
                'institute',
                'department',
                'profession',
                'budget',
                'process_status',
                'hierarchy_level',
                'created_by',
                'project_status',
                'assign_by',
                'assign_date',
                'projectduration',
            );

        // Use OR conditions to avoid filtering out entries incorrectly
        $query->where(function ($q) use ($positions, $created_by) {
            if (in_array('7', $positions)) {
                $q->orWhereHas('writerData', function ($subQuery) use ($created_by) {
                    $subQuery->where('assign_user', $created_by)
                        ->whereIn('status', ['to_do', 'on_going', 'need_support', 'correction', 'plag_correction'])
                        ->orderBy('created_at', 'desc');
                });
            }

            if (in_array('8', $positions)) {
                $q->orWhereHas('reviewerData', function ($subQuery) use ($created_by) {
                    $subQuery->where('assign_user', $created_by)
                        ->whereIn('status', ['to_do', 'on_going', 'need_support', 'correction', 'plag_correction'])
                        ->orderBy('created_at', 'desc');
                });
            }

            if (in_array('11', $positions)) {
                $q->orWhereHas('statisticanData', function ($subQuery) use ($created_by) {
                    $subQuery->where('assign_user', $created_by)
                        ->whereIn('status', ['to_do', 'on_going', 'need_support', 'correction', 'plag_correction'])
                        ->orderBy('created_at', 'desc');
                });
            }
        });

        $entries = $query->get();

        foreach ($entries as $entry) {
            if ($entry->hierarchy_level === 'urgent_important') {
                $urgentImportantCount++;
            }

            if ($entry->hierarchy_level === 'important_not_urgent') {
                $importantNotUrgentCount++;
            }

            if ($entry->hierarchy_level === 'urgent_not_important') {
                $urgentNotImportantCount++;
            }

            if ($entry->hierarchy_level === 'not_urgent_not_important') {
                $notUrgentNotImportantCount++;
            }

            if ($entry->relationLoaded('writerData')) {
                $filteredWriters = $entry->writerData->where('assign_user', $created_by);
                if ($filteredWriters->isNotEmpty()) {
                    $writerCount += $filteredWriters->count();
                }
            }

            if ($entry->relationLoaded('reviewerData')) {
                $filteredReviewers = $entry->reviewerData->where('assign_user', $created_by);
                if ($filteredReviewers->isNotEmpty()) {
                    $reviewerCount += $filteredReviewers->count();
                }
            }

            if ($entry->relationLoaded('statisticanData')) {
                $filteredReviewers = $entry->statisticanData->where('assign_user', $created_by);
                if ($filteredReviewers->isNotEmpty()) {
                    $statisticanCount += $filteredReviewers->count();
                }
            }
            if ($entry->type_of_work === 'manuscript' || $entry->type_of_work === 'thesis') {
                $data = ($entry->type_of_work === 'manuscript') ? $manuscriptData : $thesisData;

                foreach ($entry->writerData as $writer) {
                    $data['completed']['writer'] += ($writer->status === 'completed') ? 1 : 0;
                    $data['plag_correction']['writer'] += ($writer->status === 'plag_correction') ? 1 : 0;
                    $data['ongoing']['writer'] += ($writer->status === 'on_going' || $writer->status === 'need_support') ? 1 : 0;
                    foreach ($correctionStatuses as $status) {
                        $data['correction']['writer'] += ($writer->status === $status) ? 1 : 0;
                    }
                }
                foreach ($entry->reviewerData as $writer) {
                    $data['completed']['reviewing'] += ($writer->status === 'completed') ? 1 : 0;
                    $data['plag_correction']['reviewing'] += ($writer->status === 'plag_correction') ? 1 : 0;
                    $data['ongoing']['reviewing'] += ($writer->status === 'on_going' || $writer->status === 'need_support') ? 1 : 0;

                    foreach ($correctionStatuses as $status) {
                        $data['correction']['reviewing'] += ($writer->status === $status) ? 1 : 0;
                    }
                }

                if ($entry->type_of_work === 'manuscript') {
                    $manuscriptData = $data;
                } else {
                    $thesisData = $data;
                }
            }

            foreach ($entry->statisticanData as $writer) {
                $manuscriptData1['completed']['statistican'] += ($writer->status === 'completed') ? 1 : 0;
                $manuscriptData1['client_review']['statistican'] += ($writer->status === 'client_review') ? 1 : 0;
                $manuscriptData1['on_going']['statistican'] += ($writer->status === 'on_going' || $writer->status === 'not_assigned') ? 1 : 0;
                $manuscriptData1['correction']['statistican'] += ($writer->status === 'correction') ? 1 : 0;
                $manuscriptData1['query']['statistican'] += ($writer->status === 'query') ? 1 : 0;
            }

            $manuscriptData1 = $manuscriptData1;
        }

        function getTasksByStatus($position_e, $created_by, $statusArray, $positions)
        {
            $query = EntryProcessModel::with([
                'paymentProcess',
                'writerData',
                'reviewerData',
                'statisticanData',
            ])
                ->select('id', 'title', 'type_of_work', 'process_status', 'project_id', 'entry_date', 'hierarchy_level', 'created_at')
                ->where('is_deleted', 0)
                ->orderBy('created_at', 'desc');

            // Filter based on position
            if ($position_e === 'writer') {
                $query->whereHas('writerData', function ($q) use ($created_by, $statusArray) {
                    $q->where('assign_user', $created_by);
                    if ($statusArray) {
                        $q->whereIn('status', $statusArray);
                    }
                });
                // ->with('writerData', 'reviewerData', 'statisticanData');
            }

            if ($position_e === 'reviewer') {
                $query->whereHas('reviewerData', function ($q) use ($created_by, $statusArray) {
                    $q->where('assign_user', $created_by);
                    if ($statusArray) {
                        $q->whereIn('status', $statusArray);
                    }
                });
                // ->with('writerData', 'reviewerData', 'statisticanData');
            }

            if ($position_e === 'statistican') {
                $query->whereHas('statisticanData', function ($q) use ($created_by, $statusArray) {
                    $q->where('assign_user', $created_by);
                    if ($statusArray) {
                        $q->whereIn('status', $statusArray);
                    }
                });
                // ->with('writerData', 'reviewerData', 'statisticanData');
            }

            return $query;
        }

        $projectids = $entries->pluck('id')->toArray();
        // Collect tasks
        $tasks = collect();

        if (in_array('7', $positions)) {
            $writerTasks = getTasksByStatus('writer', $created_by, ['to_do'], $positions)
                ->get();
            $tasks = $tasks->merge($writerTasks);
        }

        if (in_array('8', $positions)) {
            $reviewerTasks = getTasksByStatus('reviewer', $created_by, ['to_do'], $positions)
                ->get();
            $tasks = $tasks->merge($reviewerTasks);
        }

        if (in_array('11', $positions)) {
            $statisticanTasks = getTasksByStatus('statistican', $created_by, ['to_do'], $positions)
                ->get();
            $tasks = $tasks->merge($statisticanTasks);
        }

        $tasks = $tasks->groupBy('project_id')->map(function ($taskGroup) {
            return $taskGroup->first();
        });
        $tasksCount = $tasks->count();
        $tasks = $tasks->values();

        //on going

        $ongoings = collect();
        if (in_array('7', $positions)) {
            $writerTasks = getTasksByStatus('writer', $created_by, ['on_going'], $positions)
                ->get();
            $ongoings = $ongoings->merge($writerTasks);
        }

        if (in_array('8', $positions)) {
            $reviewerTasks = getTasksByStatus('reviewer', $created_by, ['on_going'], $positions)
                ->whereHas('writerData', function ($query) use ($projectids) {
                    $query->whereIn('project_id', $projectids)
                        // Exclude projects where any writer is in 'correction'
                        ->whereDoesntHave('projectData.writerData', function ($subQuery) {
                            $subQuery->where('status', 'correction');
                        });
                })
                ->get();
            $ongoings = $ongoings->merge($reviewerTasks);
        }

        if (in_array('11', $positions)) {
            $statisticanTasks = getTasksByStatus('statistican', $created_by, ['on_going'], $positions)
                ->get();
            $ongoings = $ongoings->merge($statisticanTasks);
        }

        $ongoings = $ongoings->groupBy('project_id')->map(function ($taskGroup) {
            return $taskGroup->first();
        });
        $ongoingCount = $ongoings->count();
        $ongoing = $ongoings->values();

        //need support

        $needSupport = collect();
        if (in_array('7', $positions)) {
            $writerTasks = getTasksByStatus('writer', $created_by, ['need_support'], $positions)
                ->get();
            $needSupport = $needSupport->merge($writerTasks);
        }

        if (in_array('8', $positions)) {
            $reviewerTasks = getTasksByStatus('reviewer', $created_by, ['need_support'], $positions)
                ->get();
            $needSupport = $needSupport->merge($reviewerTasks);
        }

        if (in_array('11', $positions)) {
            $statisticanTasks = getTasksByStatus('statistican', $created_by, ['need_support'], $positions)
                ->get();
            $needSupport = $needSupport->merge($statisticanTasks);
        }

        $needSupport = $needSupport->groupBy('project_id')->map(function ($taskGroup) {
            return $taskGroup->first();
        });
        $needSupportCount = $needSupport->count();
        $needSupport = $needSupport->values();

        //client_review
        $clientReview = collect();
        if (in_array('11', $positions)) {
            $statisticanTasks = getTasksByStatus('statistican', $created_by, ['client_review'], $positions)
                ->get();
            $clientReview = $clientReview->merge($statisticanTasks);
        }
        $clientReview = $clientReview->groupBy('project_id')->map(function ($taskGroup) {
            return $taskGroup->first();
        });
        $clientReviewCount = $clientReview->count();
        $clientReview = $clientReview->values();
        //plag correction
        $pc = collect();

        if (in_array('7', $positions)) {
            $writerTasks = getTasksByStatus('writer', $created_by, ['plag_correction'], $positions)
                ->get();
            $pc = $pc->merge($writerTasks);
        }

        if (in_array('8', $positions)) {
            $reviewerTasks = getTasksByStatus('reviewer', $created_by, ['plag_correction'], $positions)
                ->get();
            $pc = $pc->merge($reviewerTasks);
        }

        $pc = $pc->groupBy('project_id')->map(function ($taskGroup) {
            return $taskGroup->first();
        });

        $pcCount = $pc->count();
        $pcList = $pc->values();
        //Client review

        $reviews = collect();

        if (in_array('11', $positions)) {
            $statisticanTasks = getTasksByStatus('statistican', $created_by, ['client_review'], $positions)
                // ->whereYear('entry_date', $currentYear)
                ->get();
            $reviews = $reviews->merge($statisticanTasks);
        }

        $reviews = $reviews->groupBy('project_id')->map(function ($taskGroup) {
            return $taskGroup->first();
        });
        $reviewsCount = $reviews->count();

        $reviews = $reviews->values();

        $completed = collect();

        if (in_array('7', $positions)) {
            $completed = $completed->merge(
                getTasksByStatus('writer', $created_by, ['completed'], $positions)
                    ->get()
            );
            $completed = $completed->merge($completed);
        }

        if (in_array('8', $positions)) {
            $completed = $completed->merge(
                getTasksByStatus('reviewer', $created_by, ['completed'], $positions)
                    ->get()
            );
            $completed = $completed->merge($completed);
        }

        if (in_array('11', $positions)) {
            $statisticanTasks = getTasksByStatus('statistican', $created_by, ['completed'], $positions)
                ->get();
            $completed = $completed->merge($statisticanTasks);
        }

        $completed = $completed->groupBy('project_id')->map(function ($taskGroup) {
            return $taskGroup->first();
        });
        $completedCount = $completed->count();

        $completed = $completed->values();

        $corrections = collect();

        if (in_array('7', $positions)) {
            $corrections = $corrections->merge(
                getTasksByStatus('writer', $created_by, ['correction'], $positions)
                    ->get()
            );
            $corrections = $corrections->merge($corrections);
        }

        if (in_array('8', $positions)) {
            $corrections = $corrections->merge(
                getTasksByStatus('reviewer', $created_by, ['correction'], $positions)
                    // ->whereHas('writerData', function ($query) use ($projectids) {
                    //     $query->whereIn('project_id', $projectids)
                    //         ->where('status', '!=', 'correction');
                    // })
                    ->whereHas('writerData', function ($query) use ($projectids) {
                        $query->whereIn('project_id', $projectids)
                            // Exclude projects where any writer is in 'correction'
                            ->whereDoesntHave('projectData.writerData', function ($subQuery) {
                                $subQuery->where('status', 'correction');
                            });
                    })
                    ->get()
            );
            $corrections = $corrections->merge($corrections);
        }

        if (in_array('11', $positions)) {
            $statisticanTasks = getTasksByStatus('statistican', $created_by, ['correction'], $positions)
                ->get();
            $corrections = $corrections->merge($statisticanTasks);
        }

        $corrections = $corrections->groupBy('project_id')->map(function ($taskGroup) {
            return $taskGroup->first();
        });

        $correctionsCount = $corrections->count();

        $corrections = $corrections->values();

        $filteredCount = $entries->count();
        $totalCount = EntryProcessModel::select('id')->where('is_deleted', 0)
            ->count();

        $responseData = [
            'emergencywork' => $urgentImportantCount,
            'importantNotUrgentCount' => $importantNotUrgentCount,
            'urgentNotImportantCount' => $urgentNotImportantCount,
            'notUrgentNotImportantCount' => $notUrgentNotImportantCount,
            // 'not_assigned' => $notAssignedCount,
            'manuscript_data' => $manuscriptData,
            'thesisData' => $thesisData,
            'manuscript_data1' => $manuscriptData1,
            // 'filtered_count' => $filteredCount,
            // 'total_count' => $totalCount,
            'position' => $position,
            'to_do_data' => $tasks,
            'to_do_count' => $tasksCount,
            'ongoing' => $ongoing,
            'ongoingCount' => $ongoingCount,
            'needSupport' => $needSupport,
            'needSupportCount' => $needSupportCount,
            'clientReview' => $clientReview,
            'clientReviewCount' => $clientReviewCount,
            'pcList' => $pcList,
            'pcCount' => $pcCount,
            'reviews' => $reviews,
            // 'reviewsCount' => $reviewsCount,
            'completed' => $completed,
            // 'completedCount' => $completedCount,
            'corrections' => $corrections,
            'correctionsCount' => $correctionsCount,
            'writerCount' => $writerCount,
            'reviewerCount' => $reviewerCount,
            'statisticanCount' => $statisticanCount,
        ];

        return response()->json($responseData);
    }

    public function monthWiseTable($position, $fromDate = null, $toDate = null)
    {
        if ($position === 'project_manager' || $position === 'admin' || $position === 'team_coordinator') {
            // Get entries for the current year
            $currentYear = date('Y');
            if (! $fromDate) {
                $fromDate = date('Y-m-d');
            }
            if (! $toDate) {
                $toDate = date('Y-m-d');
            }
            // dd($selectedMonth);
            $entries = EntryProcessModel::select('id', 'entry_date', 'type_of_work')->where('is_deleted', 0)
                ->whereDate('entry_date', '>=', $fromDate)
                ->whereDate('entry_date', '<=', $toDate)
                // ->whereYear('entry_date', $currentYear)
                ->get();

            $settingentries = Setting::first();

            if ($settingentries->project_target) {
                $targetval = $settingentries->project_target;
            } else {
                $targetval = 92;
            }

            // Initialize an array to store monthly data
            $monthlyData = [];
            $footerTotals = [
                'manuscript' => 0,
                'statistics' => 0,
                'thesis' => 0,
                'others' => 0,
                'total' => 0,
                'manuscript_percentage' => 0,
                'statistics_percentage' => 0,
                'thesis_percentage' => 0,
                'others_percentage' => 0,
                'target_percentage' => 0,
                // 'target_percentages' => 0,
                'not_assigned' => 0,
                'withdrawn' => 0,
            ];

            // Loop through each month of the year
            for ($month = 1; $month <= 12; $month++) {
                $monthEntries = $entries->filter(function ($entry) use ($month) {
                    return date('m', strtotime($entry->entry_date)) == $month;
                });

                // Get counts of 'not_assigned' and 'withdrawal' projects for this month
                $notAssignEntries = EntryProcessModel::where('is_deleted', 0)
                    ->where('process_status', 'not_assigned')
                    // ->whereYear('entry_date', $currentYear)
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate)
                    ->whereMonth('entry_date', $month)
                    ->count();

                $withdrawEntries = EntryProcessModel::where('is_deleted', 0)
                    ->where('process_status', 'withdrawal')
                    // ->whereYear('entry_date', $currentYear)
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate)
                    ->whereMonth('entry_date', $month)
                    ->count();

                $typeOfWorkCounts = [
                    'manuscript' => 0,
                    'thesis' => 0,
                    'statistics' => 0,
                    'presentation' => 0,
                    'others' => 0,
                ];

                // Count entries by type_of_work
                foreach ($monthEntries as $entry) {
                    if (isset($typeOfWorkCounts[$entry->type_of_work])) {
                        $typeOfWorkCounts[$entry->type_of_work]++;
                    }
                }

                $totalCount = array_sum($typeOfWorkCounts);

                $totalCount = max(0, $totalCount - ($notAssignEntries + $withdrawEntries));

                // Calculate percentages for each type_of_work
                $percentages = [];
                foreach ($typeOfWorkCounts as $key => $count) {

                    $percentages[$key] = $totalCount > 0 ? round(($count / $totalCount) * 100, 2) : 0;
                }

                // Calculate target percentage (based on a specific calculation you want)
                $targetPercentage = $totalCount > 0 ? round(($totalCount / $targetval) * 100, 2) : 0;
                $targetPercentages = $totalCount > 0 ? round(($totalCount / $targetval) * 12, 2) : 0;

                $manuscript_percentage = $totalCount > 0
                    ? round(($typeOfWorkCounts['manuscript'] / $totalCount) * 100, 2)
                    : 0;

                $statistics_percentage = $totalCount > 0
                    ? round(($typeOfWorkCounts['statistics'] / $totalCount) * 100, 2)
                    : 0;

                $thesis_percentage = $totalCount > 0
                    ? round(($typeOfWorkCounts['thesis'] / $totalCount) * 100, 2)
                    : 0;

                $others_percentage = $totalCount > 0
                    ? round(($typeOfWorkCounts['others'] / $totalCount) * 100, 2)
                    : 0;

                // Add data for the current month
                $monthlyData[] = [
                    'month' => date('F', mktime(0, 0, 0, $month, 1)),
                    'manuscript' => $typeOfWorkCounts['manuscript'],
                    'statistics' => $typeOfWorkCounts['statistics'],
                    'thesis' => $typeOfWorkCounts['thesis'],
                    'others' => $typeOfWorkCounts['others'],
                    // 'presentation' => $typeOfWorkCounts['presentation'],
                    // 'total' => $totalCount,
                    'total' => $totalCount,
                    'manuscript_percentage' => $manuscript_percentage,
                    'statistics_percentage' => $statistics_percentage,
                    'thesis_percentage' => $thesis_percentage,
                    'others_percentage' => $others_percentage,
                    'target_percentage' => $targetPercentage,
                    'not_assigned' => $notAssignEntries,
                    'withdrawn' => $withdrawEntries,
                ];

                // Accumulate footer totals
                $footerTotals['manuscript'] += $typeOfWorkCounts['manuscript'];
                $footerTotals['statistics'] += $typeOfWorkCounts['statistics'];
                $footerTotals['thesis'] += $typeOfWorkCounts['thesis'];
                // $footerTotals['presentation'] += $typeOfWorkCounts['presentation'];
                $footerTotals['others'] += $typeOfWorkCounts['others'];
                $footerTotals['total'] += $totalCount;
                $footerTotals['manuscript_percentage'] += $manuscript_percentage;
                $footerTotals['statistics_percentage'] += $statistics_percentage;
                $footerTotals['thesis_percentage'] += $thesis_percentage;
                $footerTotals['others_percentage'] += $others_percentage;

                $footerTotals['target_percentage'] += $targetPercentages;
                // $footerTotals['target_percentages'] += $targetPercentages;
                $footerTotals['not_assigned'] += $notAssignEntries;
                $footerTotals['withdrawn'] += $withdrawEntries;
            }

            // $monthlyCount = count($monthlyData);
            // if ($monthlyCount > 0) {
            //     $footerTotals['manuscript_percentage'] = round($footerTotals['manuscript_percentage'] / $monthlyCount, 2);
            //     $footerTotals['statistics_percentage'] = round($footerTotals['statistics_percentage'] / $monthlyCount, 2);
            //     $footerTotals['thesis_percentage'] = round($footerTotals['thesis_percentage'] / $monthlyCount, 2);
            //     $footerTotals['target_percentage'] = round($footerTotals['target_percentage'] / $monthlyCount, 2);
            // }

            return [
                'monthlyData' => $monthlyData,
                'footerTotals' => $footerTotals,
            ];
        }

        return [];
    }

    public function projectStatusView(Request $request, $id)
    {
        // $id = $request->project_id;

        $createdby = $request->created_by;
        $project = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'hierarchy_level', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->find($id);

        $projectstatus = ProjectStatus::where('project_id', $id)->where('assign_id', $createdby)->first();
        if ($project) {
            if ($request->has('status') && $request->type !== 'assignstatus') {
                $project->process_status = $request->status;
                if (! empty($request->status)) {
                    $comments = new ProjectViewStatus;
                    $comments->project_id = $id;
                    $comments->project_status = $request->status;
                    $comments->created_by = $request->createdby;
                    $comments->created_date = date('Y-m-d H:i:s');
                    $comments->save();
                }

                $activity = new ProjectActivity;
                $activity->project_id = $id;
                $activity->activity = 'Project status successfully';
                $activity->created_by = $request->createdby;
                $activity->created_date = date('Y-m-d H:i:s');
                $activity->save();
            }
            if ($request->has('assign_by')) {
                $project->assign_by = $request->assign_by;
                $project->assign_date = Carbon::now();

                if ($request->position === '7') {
                    $project->writer = $request->assign_by;
                    $project->writer_assigned_date = Carbon::now()->toDateString();
                }

                if ($request->position === '8') {
                    $project->reviewer = $request->assign_by;
                    $project->reviewer_assigned_date = Carbon::now()->toDateString();
                }

                if ($request->position === '11') {
                    $project->statistican = $request->assign_by;
                    $project->statistican_assigned_date = Carbon::now()->toDateString();
                }

                if ($request->position === '27') {
                    $project->journal = $request->assign_by;
                    $project->journal_assigned_date = Carbon::now()->toDateString();
                }
            }

            if ($request->type === 'assignstatus') {
                if ($request->position === 'writer') {
                    $projecetAssign = ProjectAssignDetails::where('project_id', $id)
                        ->where('assign_user', $createdby)
                        ->where('type', 'writer')
                        ->first();

                    if ($projecetAssign) {
                        $projecetAssign->status = $request->status;
                        $projecetAssign->created_by = $request->created_by;
                        $projecetAssign->created_at = Carbon::now();
                        $projecetAssign->updated_at = Carbon::now();
                        $projecetAssign->save();
                    }

                    $userDetails = User::find($createdby);

                    $activity = new ProjectActivity;
                    $activity->project_id = $id;
                    $activity->activity = 'Projected marked as '.$request->status.' by '.$userDetails->employee_name.'(Writer)';
                    $activity->role = 'Writer';
                    $activity->created_by = $request->created_by;
                    $activity->created_date = now();
                    $activity->save();

                    // $positions = [13, 14, 'Admin'];

                    // $users = User::whereIn('position', $positions)
                    //     ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                    //     ->get()
                    //     ->keyBy('position');

                    // $emails = [
                    //     'projectManager' => $users->get(13)?->email_address,
                    //     'teamManager' => $users->get(14)?->email_address,

                    //     'adminEmail' => $users->get('Admin')?->email_address,

                    // ];

                    // $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $request->created_by)->first();
                    // $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

                    // if (! empty($emails['projectManager']) && ! empty($emails['teamManager']) && ! empty($emails['adminEmail']) && ! empty($employeedetails->email_address)) {
                    //     try {
                    //         // Send email to writer with CC to others
                    //         Mail::to($emails['projectManager'], $emails['teamManager'])
                    //             ->cc($emails['adminEmail'])
                    //             ->send(new TaskCompleteEmail([
                    //                 'projectManagerEmail' => $emails['projectManager'],
                    //                 'teamManagerEmail' => $emails['teamManager'],
                    //                 'adminEmail' => $emails['adminEmail'],
                    //                 'writer_status' => $project->process_status,
                    //                 'employee_name' => $employeedetails->employee_name,
                    //                 'role' => $employeedetails->createdByUser?->name,
                    //                 'phone_number' => $employeedetails->phone_number,
                    //                 'project_id' => $projectDetails->project_id,
                    //                 'status' => $request->status,
                    //                 'detail_name' => $request->status,
                    //             ]));

                    //         return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
                    //     } catch (\Exception $e) {
                    //         Log::error('Mail failed: '.$e->getMessage());

                    //         return response()->json(['success' => false, 'message' => 'Failed to send email.']);
                    //     }
                    // } else {
                    //     Log::error('One or more email addresses are missing.');

                    //     return response()->json(['success' => false, 'message' => 'One or more email addresses are missing.']);
                    // }

                    $updateCreated_at = EntryProcessModel::where('id', $id)->first();
                    if ($updateCreated_at) {
                        $updateCreated_at->created_at = Carbon::now();
                        $updateCreated_at->save();
                    }

                    // $projecetAssign_tc = ProjectAssignDetails::where('project_id', $id)

                    //     ->where('type', 'team_coordinator')
                    //     // ->where('status', 'correction')
                    //     ->latest()
                    //     ->first();
                    // $projectAssign_tc_writer = ProjectAssignDetails::where('project_id', $id)
                    //     ->where('type', 'writer')->exists();
                    // $projectAssign_tc_entry = EntryProcessModel::where('id', $id)
                    //     ->where('type_of_work', 'thesis')
                    //     ->exists();

                    // if ($projecetAssign_tc && $projectAssign_tc_writer && $projectAssign_tc_entry) {
                    //     $projecetAssign_tc->assign_user = $request->created_by;
                    //     $projecetAssign_tc->status = $request->status;
                    //     $projecetAssign_tc->type_sme = '2nd_writer';
                    //     $projecetAssign_tc->created_by = $request->created_by;
                    //     $projecetAssign_tc->save();
                    // } else {
                    //     $projecetAssign_tc->assign_user = $request->created_by;
                    //     $projecetAssign_tc->status = $request->status;
                    //     $projecetAssign_tc->type_sme = 'writer';
                    //     $projecetAssign_tc->created_by = $request->created_by;
                    //     $projecetAssign_tc->save();
                    // }
                    $projectAssignTc = ProjectAssignDetails::where('project_id', $id)
                        ->where('type', 'team_coordinator')
                        // ->where('status', 'correction')
                        ->latest()
                        ->first();

                    $hasWriter = ProjectAssignDetails::where('project_id', $id)
                        ->where('type', 'writer')
                        ->count();

                    $hasThesisEntry = EntryProcessModel::where('id', $id)
                        ->where('type_of_work', 'thesis')
                        ->exists();

                    if ($projectAssignTc) {

                        $projectAssignTc->assign_user = $createdby;
                        $projectAssignTc->status = $request->status;
                        $projectAssignTc->created_by = $request->created_by;

                        if ($hasWriter > 1 && $hasThesisEntry) {
                            $projectAssignTc->type_sme = '2nd_writer';
                        } else {
                            $projectAssignTc->type_sme = 'writer';
                        }

                        $projectAssignTc->save();
                    }
                    $projecetAssign_pub = ProjectAssignDetails::where('project_id', $id)

                        ->where('type', 'publication_manager')
                        // ->where('status', 'correction')
                        ->latest()
                        ->first();

                    if ($projecetAssign_pub) {
                        $projecetAssign_pub->update([
                            'status' => '-',
                            'created_by' => $createdby,
                            'updated_at' => now(),
                        ]);
                    }

                    $checkproject_log = null;
                    if ($projecetAssign) {
                        $checkproject_log = ProjectLogs::where('project_id', $id)
                            ->where('employee_id', $projecetAssign->assign_user)
                            ->where('status_type', 'writer')
                            ->latest()
                            ->first();

                        ProjectLogs::create([
                            'project_id' => $id,
                            'employee_id' => $projecetAssign->assign_user,
                            'assigned_date' => $projecetAssign->assign_date,
                            'status' => $projecetAssign->status,
                            'status_date' => $projecetAssign->status_date,
                            'status_type' => 'writer',
                            'assing_preview_id' => optional($checkproject_log)->id,
                            'created_by' => $request->created_by,
                            'created_date' => date('Y-m-d H:i:s'),
                        ]);
                    }

                    AssigneeStatus::create([
                        'project_id' => $id,
                        'activity' => $request->status,
                        'created_by' => $request->created_by,
                        'createdby_name' => $request->createdby_name,
                        'created_date' => date('Y-m-d H:i:s'),
                    ]);

                    $positions = [13, 14, 'Admin'];

                    $users = User::whereIn('position', $positions)
                        ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                        ->get()
                        ->keyBy('position');

                    $emails = [
                        'projectManager' => $users->get(13)?->email_address,
                        'teamManager' => $users->get(14)?->email_address,

                        'adminEmail' => $users->get('Admin')?->email_address,

                    ];

                    $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $request->created_by)->first();
                    $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

                    if (! empty($emails['projectManager']) && ! empty($emails['teamManager']) && ! empty($emails['adminEmail']) && ! empty($employeedetails->email_address)) {
                        try {
                            // Send email to writer with CC to others
                            Mail::to($emails['projectManager'], $emails['teamManager'])
                                ->cc($emails['adminEmail'])
                                ->send(new TaskCompleteEmail([
                                    'projectManagerEmail' => $emails['projectManager'],
                                    'teamManagerEmail' => $emails['teamManager'],
                                    'adminEmail' => $emails['adminEmail'],
                                    'writer_status' => $project->process_status,
                                    'employee_name' => $employeedetails->employee_name,
                                    'role' => $employeedetails->createdByUser?->name,
                                    'phone_number' => $employeedetails->phone_number,
                                    'project_id' => $projectDetails->project_id,
                                    'status' => $request->status,
                                    'detail_name' => $request->status,
                                ]));

                            return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
                        } catch (\Exception $e) {
                            Log::error('Mail failed: '.$e->getMessage());

                            return response()->json(['success' => false, 'message' => 'Failed to send email.']);
                        }
                    } else {
                        Log::error('One or more email addresses are missing.');

                        return response()->json(['success' => false, 'message' => 'One or more email addresses are missing.']);
                    }
                }
                if ($request->position === 'reviewer') {
                    // Update reviewer assignment
                    $projecetAssign = ProjectAssignDetails::where('project_id', $id)
                        ->where('assign_user', $createdby)
                        ->where('type', 'reviewer')
                        ->first();

                    if ($projecetAssign) {
                        $projecetAssign->status = $request->status;
                        $projecetAssign->created_by = $request->created_by;
                        $projecetAssign->created_at = Carbon::now();
                        $projecetAssign->updated_at = Carbon::now();
                        $projecetAssign->save();
                    }

                    $userDetails = User::find($createdby);

                    $activity = new ProjectActivity;
                    $activity->project_id = $id;
                    $activity->activity = 'Projected marked as '.$request->status.' by '.$userDetails->employee_name.'(Reviewer)';
                    $activity->role = 'Reviewer';
                    $activity->created_by = $request->created_by;
                    $activity->created_date = now();
                    $activity->save();

                    // $positions = [13, 14, 'Admin'];

                    // $users = User::whereIn('position', $positions)
                    //     ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                    //     ->get()
                    //     ->keyBy('position');

                    // $emails = [
                    //     'projectManager' => $users->get(13)?->email_address,
                    //     'teamManager' => $users->get(14)?->email_address,

                    //     'adminEmail' => $users->get('Admin')?->email_address,

                    // ];

                    // $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $request->created_by)->first();
                    // $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

                    // if (! empty($emails['projectManager']) && ! empty($emails['teamManager']) && ! empty($emails['adminEmail']) && ! empty($employeedetails->email_address)) {
                    //     try {
                    //         // Send email to writer with CC to others
                    //         Mail::to($emails['projectManager'], $emails['teamManager'])
                    //             ->cc($emails['adminEmail'])
                    //             ->send(new TaskCompleteEmail([
                    //                 'projectManagerEmail' => $emails['projectManager'],
                    //                 'teamManagerEmail' => $emails['teamManager'],
                    //                 'adminEmail' => $emails['adminEmail'],
                    //                 'writer_status' => $project->process_status,
                    //                 'employee_name' => $employeedetails->employee_name,
                    //                 'role' => $employeedetails->createdByUser?->name,
                    //                 'phone_number' => $employeedetails->phone_number,
                    //                 'project_id' => $projectDetails->project_id,
                    //                 'status' => $request->status,
                    //                 'detail_name' => $request->status,
                    //             ]));

                    //         return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
                    //     } catch (\Exception $e) {
                    //         Log::error('Mail failed: '.$e->getMessage());

                    //         return response()->json(['success' => false, 'message' => 'Failed to send email.']);
                    //     }
                    // } else {
                    //     Log::error('One or more email addresses are missing.');

                    //     return response()->json(['success' => false, 'message' => 'One or more email addresses are missing.']);
                    // }

                    $updateCreated_at = EntryProcessModel::where('id', $id)->first();
                    if ($updateCreated_at) {
                        $updateCreated_at->created_at = Carbon::now();
                        $updateCreated_at->save();
                    }

                    $journal_tc = ProjectAssignDetails::where('project_id', $id)
                        ->where('type', 'publication_manager')
                        // ->where('status', 'reviewer_comments')
                        ->latest()
                        ->first();

                    if ($journal_tc) {
                        $journal_tc->update([
                            'status' => '-',
                            'created_by' => $createdby,
                            'updated_at' => now(),
                        ]);
                    }

                    // Update latest team coordinator assignment with status 'correction'
                    $projecetAssign_tc = ProjectAssignDetails::where('project_id', $id)
                        ->where('type', 'team_coordinator')
                        // ->where('status', 'correction')
                        ->latest()
                        ->first();

                    if ($projecetAssign_tc) {
                        $projecetAssign_tc->assign_user = $createdby;
                        $projecetAssign_tc->status = $request->status;
                        $projecetAssign_tc->type_sme = '-';
                        $projecetAssign_tc->created_by = $request->created_by;
                        $projecetAssign_tc->save();
                    }

                    // Create project log for reviewer
                    if ($projecetAssign) {
                        $checkproject_log = ProjectLogs::where('project_id', $id)
                            ->where('employee_id', $projecetAssign->assign_user)
                            ->where('status_type', 'reviewer')
                            ->latest()
                            ->first();

                        ProjectLogs::create([
                            'project_id' => $id,
                            'employee_id' => $projecetAssign->assign_user,
                            'assigned_date' => $projecetAssign->assign_date,
                            'status' => $projecetAssign->status,
                            'status_date' => $projecetAssign->status_date,
                            'status_type' => 'reviewer',
                            'assing_preview_id' => optional($checkproject_log)->id,
                            'created_by' => $request->created_by,
                            'created_date' => date('Y-m-d H:i:s'),
                        ]);
                    }

                    // Create assignee status
                    AssigneeStatus::create([
                        'project_id' => $id,
                        'activity' => $request->status,
                        'created_by' => $request->created_by,
                        'createdby_name' => $request->createdby_name,
                        'created_date' => date('Y-m-d H:i:s'),
                    ]);

                    $positions = [13, 14, 'Admin'];

                    $users = User::whereIn('position', $positions)
                        ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                        ->get()
                        ->keyBy('position');

                    $emails = [
                        'projectManager' => $users->get(13)?->email_address,
                        'teamManager' => $users->get(14)?->email_address,

                        'adminEmail' => $users->get('Admin')?->email_address,

                    ];

                    $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $request->created_by)->first();
                    $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

                    if (! empty($emails['projectManager']) && ! empty($emails['teamManager']) && ! empty($emails['adminEmail']) && ! empty($employeedetails->email_address)) {
                        try {
                            // Send email to writer with CC to others
                            Mail::to($emails['projectManager'], $emails['teamManager'])
                                ->cc($emails['adminEmail'])
                                ->send(new TaskCompleteEmail([
                                    'projectManagerEmail' => $emails['projectManager'],
                                    'teamManagerEmail' => $emails['teamManager'],
                                    'adminEmail' => $emails['adminEmail'],
                                    'writer_status' => $project->process_status,
                                    'employee_name' => $employeedetails->employee_name,
                                    'role' => $employeedetails->createdByUser?->name,
                                    'phone_number' => $employeedetails->phone_number,
                                    'project_id' => $projectDetails->project_id,
                                    'status' => $request->status,
                                    'detail_name' => $request->status,
                                ]));

                            return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
                        } catch (\Exception $e) {
                            Log::error('Mail failed: '.$e->getMessage());

                            return response()->json(['success' => false, 'message' => 'Failed to send email.']);
                        }
                    } else {
                        Log::error('One or more email addresses are missing.');

                        return response()->json(['success' => false, 'message' => 'One or more email addresses are missing.']);
                    }
                }
                if ($request->position === 'statistican') {
                    // Update statistician record
                    $projecetAssign = ProjectAssignDetails::where('project_id', $id)
                        ->where('assign_user', $createdby)
                        ->where('type', 'statistican')
                        ->first();

                    if ($projecetAssign) {
                        $projecetAssign->status = $request->status;
                        $projecetAssign->created_by = $request->created_by;
                        $projecetAssign->created_at = Carbon::now();
                        $projecetAssign->updated_at = Carbon::now();
                        $projecetAssign->save();
                    }

                    $userDetails = User::find($createdby);

                    $activity = new ProjectActivity;
                    $activity->project_id = $id;
                    $activity->activity = 'Projected marked as '.$request->status.' by '.$userDetails->employee_name.'(Statistican)';
                    $activity->role = 'Statistican';
                    $activity->created_by = $request->created_by;
                    $activity->created_date = now();
                    $activity->save();

                    // $positions = [13, 14, 'Admin'];

                    // $users = User::whereIn('position', $positions)
                    //     ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                    //     ->get()
                    //     ->keyBy('position');

                    // $emails = [
                    //     'projectManager' => $users->get(13)?->email_address,
                    //     'teamManager' => $users->get(14)?->email_address,

                    //     'adminEmail' => $users->get('Admin')?->email_address,

                    // ];

                    // $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $request->created_by)->first();
                    // $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

                    // if (! empty($emails['projectManager']) && ! empty($emails['teamManager']) && ! empty($emails['adminEmail']) && ! empty($employeedetails->email_address)) {
                    //     try {
                    //         // Send email to writer with CC to others
                    //         Mail::to($emails['projectManager'], $emails['teamManager'])
                    //             ->cc($emails['adminEmail'])
                    //             ->send(new TaskCompleteEmail([
                    //                 'projectManagerEmail' => $emails['projectManager'],
                    //                 'teamManagerEmail' => $emails['teamManager'],
                    //                 'adminEmail' => $emails['adminEmail'],
                    //                 'writer_status' => $project->process_status,
                    //                 'employee_name' => $employeedetails->employee_name,
                    //                 'role' => $employeedetails->createdByUser?->name,
                    //                 'phone_number' => $employeedetails->phone_number,
                    //                 'project_id' => $projectDetails->project_id,
                    //                 'status' => $request->status,
                    //                 'detail_name' => $request->status,
                    //             ]));

                    //         return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
                    //     } catch (\Exception $e) {
                    //         Log::error('Mail failed: '.$e->getMessage());

                    //         return response()->json(['success' => false, 'message' => 'Failed to send email.']);
                    //     }
                    // } else {
                    //     Log::error('One or more email addresses are missing.');

                    //     return response()->json(['success' => false, 'message' => 'One or more email addresses are missing.']);
                    // }

                    $updateCreated_at = EntryProcessModel::where('id', $id)->first();
                    if ($updateCreated_at) {
                        $updateCreated_at->created_at = Carbon::now();
                        $updateCreated_at->save();
                    }

                    // Find latest team coordinator record with status 'correction'
                    $projecetAssign_tc = ProjectAssignDetails::where('project_id', $id)
                        // ->where('assign_user', $createdby)
                        ->where('type', 'team_coordinator')
                        ->where('status', 'correction')
                        ->latest()
                        ->first();

                    if ($projecetAssign_tc) {
                        $projecetAssign_tc->assign_user = $createdby;
                        $projecetAssign_tc->status = $request->status;
                        $projecetAssign_tc->type_sme = 'statistican';
                        $projecetAssign_tc->created_by = $request->created_by;
                        $projecetAssign_tc->save();
                    }

                    $journal_tc = ProjectAssignDetails::where('project_id', $id)
                        ->where('type', 'publication_manager')
                        // ->where('status', 'reviewer_comments')
                        ->latest()
                        ->first();

                    if ($journal_tc) {
                        $journal_tc->update([
                            'status' => '-',
                            'created_by' => $createdby,
                            'updated_at' => now(),
                        ]);
                    }

                    $checkproject_log = ProjectLogs::where('project_id', $id)->where('employee_id', $projecetAssign->assign_user)->where('status_type', 'statistican')->latest()->first();

                    ProjectLogs::create([
                        'project_id' => $id,
                        'employee_id' => $projecetAssign->assign_user,
                        'assigned_date' => $projecetAssign->assign_date,
                        'status' => $projecetAssign->status,
                        'status_date' => $projecetAssign->status_date,
                        'status_type' => 'statistican',
                        'assing_preview_id' => ProjectLogs::where('project_id', $id)
                            ->where('employee_id', $projecetAssign->assign_user)
                            ->where('status_type', 'statistican')
                            ->orderBy('id', 'desc')
                            ->value('id'),
                        'created_by' => $request->created_by,
                        'created_date' => date('Y-m-d H:i:s'),
                    ]);

                    AssigneeStatus::create([
                        'project_id' => $id,
                        'activity' => $request->status,
                        'created_by' => $request->created_by,
                        'createdby_name' => $request->createdby_name,
                        'created_date' => date('Y-m-d H:i:s'),
                    ]);

                    $positions = [13, 14, 'Admin'];

                    $users = User::whereIn('position', $positions)
                        ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                        ->get()
                        ->keyBy('position');

                    $emails = [
                        'projectManager' => $users->get(13)?->email_address,
                        'teamManager' => $users->get(14)?->email_address,

                        'adminEmail' => $users->get('Admin')?->email_address,

                    ];

                    $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $request->created_by)->first();
                    $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

                    if (! empty($emails['projectManager']) && ! empty($emails['teamManager']) && ! empty($emails['adminEmail']) && ! empty($employeedetails->email_address)) {
                        try {
                            // Send email to writer with CC to others
                            Mail::to($emails['projectManager'], $emails['teamManager'])
                                ->cc($emails['adminEmail'])
                                ->send(new TaskCompleteEmail([
                                    'projectManagerEmail' => $emails['projectManager'],
                                    'teamManagerEmail' => $emails['teamManager'],
                                    'adminEmail' => $emails['adminEmail'],
                                    'writer_status' => $project->process_status,
                                    'employee_name' => $employeedetails->employee_name,
                                    'role' => $employeedetails->createdByUser?->name,
                                    'phone_number' => $employeedetails->phone_number,
                                    'project_id' => $projectDetails->project_id,
                                    'status' => $request->status,
                                    'detail_name' => $request->status,
                                ]));

                            return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
                        } catch (\Exception $e) {
                            Log::error('Mail failed: '.$e->getMessage());

                            return response()->json(['success' => false, 'message' => 'Failed to send email.']);
                        }
                    } else {
                        Log::error('One or more email addresses are missing.');

                        return response()->json(['success' => false, 'message' => 'One or more email addresses are missing.']);
                    }
                }

                if ($request->position === 'journal') {
                    // $project->journal_status = $request->status;
                    $projecetAssign = ProjectAssignDetails::where('project_id', $id)->where('assign_user', $createdby)->where('type', 'writer')->first();
                    $projecetAssign->status = $request->status;
                    $projecetAssign->created_by = $request->created_by;
                    $projecetAssign->created_at = Carbon::now();
                    $projecetAssign->updated_at = Carbon::now();
                    $projecetAssign->save();

                    $checkproject_log = ProjectLogs::where('project_id', $id)->where('employee_id', $projecetAssign->assign_user)->where('status_type', 'journal')->latest()->first();

                    ProjectLogs::create([
                        'project_id' => $id,
                        'employee_id' => $projecetAssign->assign_user,
                        'assigned_date' => $projecetAssign->assign_date,
                        'status' => $projecetAssign->status,
                        'status_date' => $projecetAssign->status_date,
                        'status_type' => 'journal',
                        'assing_preview_id' => $checkproject_log->id,
                        'created_by' => $request->created_by,
                        'created_date' => date('Y-m-d H:i:s'),
                    ]);

                    AssigneeStatus::create([
                        'project_id' => $id,
                        'activity' => $request->status,
                        'created_by' => $request->created_by,
                        'createdby_name' => $request->createdby_name,
                        'created_date' => date('Y-m-d H:i:s'),
                    ]);
                }

                // if ($request->position === 'journal') {
                //     $project->journal_status = $request->status;
                //     ProjectLogs::create([
                //         'project_id' => $id,
                //         'employee_id' => $project->journal,
                //         'assigned_date' => $project->journal_assigned_date,
                //         'status' => $request->status,
                //         'status_date' => $project->journal_status_date,
                //         'status_type' => 'journal',
                //         'created_by' => $request->created_by,
                //         'created_date' => date('Y-m-d H:i:s')
                //     ]);

                //     AssigneeStatus::create([
                //         'project_id' => $id,
                //         'activity' => $request->status,
                //         'created_by' => $request->created_by,
                //         'createdby_name' => $request->createdby_name,
                //         'created_date' => date('Y-m-d H:i:s')
                //     ]);
                // }

                $activity = new ProjectActivity;
                $activity->project_id = $id;
                $activity->activity = 'status updated successfully';
                $activity->created_by = $request->created_by;
                $activity->created_date = date('Y-m-d H:i:s');
                $activity->save();
            }

            $project->save();

            $emailcheck = false;

            if ($request->type === 'accepted' || $request->type === 'rejected') {
                $projectstatus = ProjectStatus::where('project_id', $id)->where('assign_id', $createdby)->first();
                $created = User::with('createdByUser')->find($createdby);
                $creator = $created?->createdByUser?->name ?? 'Admin';
                // $activity = new ProjectActivity;
                // $activity->project_id = $id;
                // if ($request->type === 'accepted') {
                //     $activity->activity = 'accepted successfully';
                // } else {
                //     $activity->activity = 'Project rejected successfully';
                // }
                // $activity->role = $creator;
                // $activity->created_by = $request->created_by;
                // $activity->created_date = date('Y-m-d H:i:s');
                // $activity->save();

                if ($projectstatus) {
                    if ($request->project_status === 'writer') {

                        $projecetAssign = ProjectAssignDetails::where('project_id', $id)->where('assign_user', $createdby)->where('type', 'writer')->first();
                        $projecetAssign->status = 'on_going';
                        $projecetAssign->created_at = Carbon::now();
                        $projecetAssign->updated_at = Carbon::now();
                        $projecetAssign->save();

                        $userDetails = User::find($createdby);

                        $activity = new ProjectActivity;
                        $activity->project_id = $id;
                        $activity->activity = 'Project '.$request->type.' to '.$userDetails->employee_name.'(writer)';
                        $activity->role = 'writer';
                        $activity->created_by = $request->created_by;
                        $activity->created_date = now();
                        $activity->save();

                        $checkproject_log = ProjectLogs::where('project_id', $id)->where('employee_id', $projecetAssign->assign_user)->where('status_type', 'writer')->latest()->first();

                        $projectLog = new ProjectLogs;
                        $projectLog->project_id = $id;
                        $projectLog->employee_id = $createdby;
                        $projectLog->assigned_date = $projecetAssign->assign_date;
                        $projectLog->status = 'on_going';
                        $projectLog->status_date = Carbon::now();
                        $projectLog->status_type = 'writer';
                        // $projectLog->assing_preview_id = $checkproject_log->id;
                        $projectLog->assing_preview_id = ProjectLogs::where('project_id', $id)
                            ->where('employee_id', $projecetAssign->assign_user)
                            ->orderBy('id', 'desc')
                            ->value('id');
                        $projectLog->created_by = $request->created_by;
                        $projectLog->created_date = date('Y-m-d H:i:s');
                        $projectLog->save();
                        $projectstatus->status = $request->type;
                    }

                    if ($request->project_status === 'reviewer') {
                        $projecetAssign = ProjectAssignDetails::where('project_id', $id)->where('assign_user', $createdby)->where('type', 'reviewer')->first();
                        $projecetAssign->status = 'on_going';
                        $projecetAssign->created_at = Carbon::now();
                        $projecetAssign->updated_at = Carbon::now();
                        $projecetAssign->save();

                        $userDetails = User::find($createdby);

                        $activity = new ProjectActivity;
                        $activity->project_id = $id;
                        $activity->activity = 'Project '.$request->type.' to '.$userDetails->employee_name.'(reviewer)';
                        $activity->role = 'reviewer';
                        $activity->created_by = $request->created_by;
                        $activity->created_date = now();
                        $activity->save();

                        $checkproject_log = ProjectLogs::where('project_id', $id)->where('employee_id', $projecetAssign->assign_user)->where('status_type', 'reviewer')->latest()->first();

                        $projectLog = new ProjectLogs;
                        $projectLog->project_id = $id;
                        $projectLog->employee_id = $createdby;
                        $projectLog->assigned_date = $projecetAssign->assign_date;
                        $projectLog->status = 'on_going';
                        $projectLog->status_date = Carbon::now();
                        $projectLog->status_type = 'reviewer';
                        // $projectLog->assing_preview_id = $checkproject_log->id;
                        $projectLog->assing_preview_id = ProjectLogs::where('project_id', $id)
                            ->where('employee_id', $projecetAssign->assign_user)
                            ->orderBy('id', 'desc')
                            ->value('id');
                        $projectLog->created_by = $request->created_by;
                        $projectLog->created_date = date('Y-m-d H:i:s');
                        $projectLog->save();

                        $projectstatus->status = $request->type;
                    }

                    if ($request->project_status === 'statistican') {
                        $projecetAssign = ProjectAssignDetails::where('project_id', $id)->where('assign_user', $createdby)->where('type', 'statistican')->first();
                        $projecetAssign->status = 'on_going';
                        $projecetAssign->created_at = Carbon::now();
                        $projecetAssign->updated_at = Carbon::now();
                        $projecetAssign->save();

                        $userDetails = User::find($createdby);

                        $activity = new ProjectActivity;
                        $activity->project_id = $id;
                        $activity->activity = 'Project '.$request->type.' to '.$userDetails->employee_name.'(statistican)';
                        $activity->role = 'statistican';
                        $activity->created_by = $request->created_by;
                        $activity->created_date = now();
                        $activity->save();

                        $checkproject_log = ProjectLogs::where('project_id', $id)->where('employee_id', $projecetAssign->assign_user)->where('status_type', 'statistican')->latest()->first();

                        $projectLog = new ProjectLogs;
                        $projectLog->project_id = $id;
                        $projectLog->employee_id = $createdby;
                        $projectLog->assigned_date = $projecetAssign->assign_date;
                        $projectLog->status = 'on_going';
                        $projectLog->status_date = Carbon::now();
                        $projectLog->status_type = 'statistican';
                        $projectLog->created_by = $request->created_by;
                        $projectLog->created_date = date('Y-m-d H:i:s');
                        $projectLog->save();

                        $projectstatus->status = $request->type;
                    }
                    $projectstatus->save();
                }

                if ($request->type === 'accepted') {
                    $emailcheck = true;
                }
            }

            if ($emailcheck == true) {

                // Prepare email recipients
                $positions = [13, 14, 'Admin'];

                $users = User::whereIn('position', $positions)
                    ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                    ->get();

                $emails = [
                    'projectManager' => $users->firstWhere('position', 13)?->email_address,
                    'teamManager' => $users->firstWhere('position', 14)?->email_address,
                    'adminEmail' => $users->firstWhere('position', 'Admin')?->email_address,
                ];

                $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $request->created_by)->first();
                $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

                // if (!empty($emails['projectManager']) && !empty($emails['teamManager']) && !empty($emails['adminEmail']) && !empty($employeedetails->email_address)) {
                //     try {
                //         // Send email to writer with CC to others
                //         Mail::to($emails['projectManager'], $emails['teamManager'])
                //             ->cc($emails['adminEmail'])
                //             ->send(new TaskEmail([
                //                 'projectManagerEmail' => $emails['projectManager'],
                //                 'teamManagerEmail' => $emails['teamManager'],
                //                 'adminEmail' => $emails['adminEmail'],
                //                 'writer_status' => $project->process_status,
                //                 'employee_name' => $employeedetails->employee_name,
                //                 'role' => $employeedetails->createdByUser?->name,
                //                 'phone_number' => $employeedetails->phone_number,
                //                 'project_id' => $projectDetails->project_id
                //             ]));

                //         return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
                //     } catch (\Exception $e) {
                //         Log::error('Mail failed: ' . $e->getMessage());

                //         return response()->json(['success' => false, 'message' => 'Failed to send email.']);
                //     }
                // } else {
                //     Log::error('One or more email addresses are missing.');
                //     return response()->json(['success' => false, 'message' => 'One or more email addresses are missing.']);
                // }
            }

            // if ($request->status === 'completed') {
            //     // Prepare email recipients
            //     $positions = [13, 14, 'Admin'];

            //     $users = User::whereIn('position', $positions)
            //         ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
            //         ->get()
            //         ->keyBy('position');

            //     $emails = [
            //         // 'projectManager' => $users->get(13)?->email_address,
            //         // 'teamManager' => $users->get(14)?->email_address,
            //         'projectManager' => 'barathkrishnamoorthy17@gmail.com',
            //         'teamManager' => 'barathkrishnamoorthy17@gmail.com',
            //         // 'adminEmail' => $users->get('Admin')?->email_address,
            //         'adminEmail' => 'barathkrishnamoorthy17@gmail.com',
            //     ];

            //     $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $request->created_by)->first();
            //     $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

            //     if (! empty($emails['projectManager']) && ! empty($emails['teamManager']) && ! empty($emails['adminEmail']) && ! empty($employeedetails->email_address)) {
            //         try {
            //             // Send email to writer with CC to others
            //             Mail::to($emails['projectManager'], $emails['teamManager'])
            //                 ->cc($emails['adminEmail'])
            //                 ->send(new TaskCompleteEmail([
            //                     'projectManagerEmail' => $emails['projectManager'],
            //                     'teamManagerEmail' => $emails['teamManager'],
            //                     'adminEmail' => $emails['adminEmail'],
            //                     'writer_status' => $project->process_status,
            //                     'employee_name' => $employeedetails->employee_name,
            //                     'role' => $employeedetails->createdByUser?->name,
            //                     'phone_number' => $employeedetails->phone_number,
            //                     'project_id' => $projectDetails->project_id,
            //                     'status' => $request->status,
            //                     'detail_name' => $request->status
            //                 ]));

            //             return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
            //         } catch (\Exception $e) {
            //             Log::error('Mail failed: '.$e->getMessage());

            //             return response()->json(['success' => false, 'message' => 'Failed to send email.']);
            //         }
            //     } else {
            //         Log::error('One or more email addresses are missing.');

            //         return response()->json(['success' => false, 'message' => 'One or more email addresses are missing.']);
            //     }
            // }
        }
    }

    public function getPendingList(Request $request, $id)
    {
        $projectDetails = EntryProcessModel::with(['writerData', 'reviewerData', 'statisticanData'])->select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'hierarchy_level', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->find($id);

        $currentDate = date('Y-m-d');
        $writerDaysCount = 0;
        if ($projectDetails && $projectDetails->writerData) {
            // Assuming 'writerData' may contain multiple entries, loop through them
            foreach ($projectDetails->writerData as $writer) {
                // Assuming 'assign_user' is the field used to reference the writer's ID
                $projectstatus = projectlogs::where('project_id', $id)
                    ->where('employee_id', $writer->assign_user) // Adjust based on your field
                    ->where('status', 'completed')
                    ->orderBy('id', 'desc')
                    ->first();

                $projectstatus_completeddate = $projectstatus ? $projectstatus->created_date : null;

                // Retrieve the project duration for the current writer
                $projectDurationDate = $writer->project_duration;
                $assignedDate = Carbon::parse($projectDurationDate);

                if (! $projectstatus_completeddate) {
                    // If there's no completed date, calculate the difference between assigned date and current date
                    if ($assignedDate < $currentDate) {
                        $writerDaysCount += $assignedDate->diffInDays($currentDate); // Add the days to the count
                    }
                } else {
                    // If there's a completed date, calculate the difference between assigned date and completed date
                    $completedDate = Carbon::parse($projectstatus_completeddate);
                    if ($assignedDate < $currentDate) {
                        $writerDaysCount += $assignedDate->diffInDays($completedDate); // Add the days to the count
                    }
                }
            }
        }
        // Calculate the days from assigned_date to today for statis logs

        $paymentsStatisLogs = ProjectLogs::where([
            ['project_id', '=', $id],
            ['status_type', '=', 'writer'],
            ['status', '=', 'completed'],
        ])->latest()->first();

        $statisticanDaysCount = 0;

        if ($projectDetails && $projectDetails->statisticanData) {
            $statisticanDaysCount = 0; // Initialize the variable to store the total delay days

            // Loop through all statistican entries if there are multiple
            foreach ($projectDetails->statisticanData as $statistican) {
                // Fetch the last completed status for each statistican
                $projectstatus = projectlogs::where('project_id', $id)
                    ->where('employee_id', $statistican->assign_user) // Assuming 'assign_user' is the reference
                    ->where('status', 'completed')
                    ->orderBy('id', 'desc')
                    ->first();

                // Get the project completed date from the log if available
                $projectstatus_completeddate = $projectstatus ? $projectstatus->created_date : null;

                // Retrieve the statistican project duration (assigned date)
                $projectDurationDate = $statistican->project_duration;
                $assignedDate = Carbon::parse($projectDurationDate);

                // If the project is completed, don't calculate the delay
                if (! $projectstatus_completeddate) {
                    // If the assigned date is before the current date, calculate the delay
                    if ($assignedDate < $currentDate) {
                        $statisticanDaysCount += $assignedDate->diffInDays($currentDate); // Add to total delay days
                    }
                } else {
                    // If there is a completed date, calculate the delay from the assigned date to the completed date
                    $completedDate = Carbon::parse($projectstatus_completeddate);
                    if ($assignedDate < $currentDate) {
                        $statisticanDaysCount += $assignedDate->diffInDays($completedDate); // Add to total delay days
                    }
                }
            }
        }
        // Get the latest reviewer log
        $paymentsReviewerLogs = ProjectLogs::where([
            ['project_id', '=', $id],
            ['status_type', '=', 'reviewer'],
            ['status', '=', 'completed'],
        ])->latest()->first();

        $reviewerDaysCount = 0;

        if ($projectDetails && $projectDetails->reviewerData) {
            $reviewerDaysCount = 0;
            foreach ($projectDetails->reviewerData as $reviewer) {
                $projectstatus = projectlogs::where('project_id', $id)
                    ->where('employee_id', $reviewer->assign_user)
                    ->where('status', 'completed')
                    ->orderBy('id', 'desc')
                    ->first();

                $projectstatus_completeddate = $projectstatus ? $projectstatus->created_date : null;

                $projectDurationDate = $reviewer->project_duration;
                $assignedDate = Carbon::parse($projectDurationDate);

                if (! $projectstatus_completeddate) {
                    if ($assignedDate < $currentDate) {
                        $reviewerDaysCount += $assignedDate->diffInDays($currentDate);
                    }
                } else {
                    $completedDate = Carbon::parse($projectstatus_completeddate);
                    if ($assignedDate < $currentDate) {
                        $reviewerDaysCount += $assignedDate->diffInDays($completedDate);
                    }
                }
            }
        }

        $projectDaysCount = 0;
        if ($projectDetails && $projectDetails->projectduration) {

            $projectstatus = ProjectViewStatus::where('project_id', $id)->Where('project_status', 'completed')->orderBy('id', 'desc')->latest()->first();

            $projectstatus_completeddate = $projectstatus ? $projectstatus->created_date : null;

            $completedDate = $projectstatus_completeddate ? date('Y-m-d', strtotime($projectstatus_completeddate)) : null;

            $assignedDate = Carbon::parse($projectDetails->projectduration);

            if ($assignedDate <= $currentDate) {
                if ($completedDate) {
                    $projectDaysCount = $assignedDate->diffInDays($completedDate);
                } else {
                    $projectDaysCount = $assignedDate->diffInDays($currentDate);
                }
            }
        }

        $completedWriterLogs = ProjectLogs::where([
            ['project_id', '=', $id],
            ['status_type', '=', 'writer'],
            ['status', '=', 'completed'],
        ])->latest()->first();

        $writerPaymentDueDate = 0;
        if ($completedWriterLogs && $completedWriterLogs->status_date) {
            $statusDate = Carbon::parse($completedWriterLogs->status_date);
            $writerPaymentDueDate = $statusDate->addDays(21)->toDateString();
        }

        $writerPaymentDueDate = null;
        $writerPaymentDueDaysCount = 0;

        if ($completedWriterLogs && $completedWriterLogs->status_date) {
            $statusDate = Carbon::parse($completedWriterLogs->status_date);

            $writerPaymentDueDate = $statusDate->addDays(21)->toDateString();

            $writerPaymentDueDaysCount = $statusDate->diffInDays(Carbon::now());
        }

        $completedReviewerLogs = ProjectLogs::where([
            ['project_id', '=', $id],
            ['status_type', '=', 'reviewer'],
            ['status', '=', 'completed'],
        ])->latest()->first();

        // Calculate the reviewer payment due date (status_date + 21 days) and days count
        $reviewerPaymentDueDate = null;
        $reviewerPaymentDueDaysCount = 0;

        if ($completedReviewerLogs && $completedReviewerLogs->status_date) {
            $statusDate = Carbon::parse($completedReviewerLogs->status_date);

            // Calculate the payment due date (status_date + 21 days)
            $reviewerPaymentDueDate = $statusDate->addDays(21)->toDateString();

            // Calculate days count from status_date to today
            $reviewerPaymentDueDaysCount = $statusDate->diffInDays(Carbon::now());
        }

        // Prepare data to return
        $data = [
            'project_id' => $projectDetails->project_id,
            'writer_days_count' => $writerDaysCount,
            'reviewer_days_count' => $reviewerDaysCount,
            'statistican_days_count' => $statisticanDaysCount,
            'project_delay_count' => $projectDaysCount,
            'writerPaymentDueDays' => $writerPaymentDueDaysCount,
            'reviewerPaymentDueDate' => $reviewerPaymentDueDaysCount,
            'writerPaymentDueDate' => $writerPaymentDueDate,
            'reviewerPaymentDueD' => $reviewerPaymentDueDate,
        ];

        // Return success response
        return response()->json([
            'success' => true,
            'message' => 'Pending list retrieved successfully',
            'data' => $data,
        ], 200);
    }

    public function getClientNotification(Request $request, $id)
    {
        $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration', 'client_name', 'contact_number')->find($id);

        if ($projectDetails) {
            if (! empty($projectDetails->email)) {
                try {
                    Mail::to($projectDetails->email)->send(new ClientEmail([
                        'client_name' => $projectDetails->client_name,
                        'type_of_work' => $projectDetails->type_of_work,
                        'project_id' => $projectDetails->project_id,
                        'project_title' => $projectDetails->title,
                        'project_status' => $projectDetails->project_status,
                        'contact_number' => $projectDetails->contact_number,
                        'pdf_path' => public_path('uploads/disclaimer.pdf'),
                    ]));

                    return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
                } catch (\Exception $e) {
                    // Log the error
                    Log::error('Mail failed: '.$e->getMessage());

                    return response()->json(['success' => false, 'message' => 'Failed to send email.']);
                }
            } else {
                Log::error('Client email is empty or invalid');

                return response()->json(['success' => false, 'message' => 'Client email is empty or invalid.']);
            }
        } else {
            Log::error('Project not found');

            return response()->json(['success' => false, 'message' => 'Project not found.']);
        }
    }

    public function getClientProjectNotification(Request $request, $id)
    {
        $createdBy = $request->query('created_by');
        $notificationV = $request->query('notification_v');

        // Get project details by ID
        $projectDetails = EntryProcessModel::select(
            'id',
            'entry_date',
            'title',
            'project_id',
            'type_of_work',
            'email',
            'institute',
            'department',
            'profession',
            'budget',
            'process_status',
            'hierarchy_level',
            'created_by',
            'project_status',
            'assign_by',
            'assign_date',
            'projectduration',
            'client_name',
            'contact_number',
            // DB::raw("CONCAT(DATEDIFF(projectduration, created_at), ' days ', MOD(TIMESTAMPDIFF(HOUR, created_at, projectduration), 24), ' hrs') AS projectduration"),
            DB::raw("CONCAT(DATEDIFF(projectduration, entry_date), ' days') AS projectduration"),
        )
            // ->with (['documents'])
            ->find($id);

        if (! $projectDetails) {
            Log::error('Project not found', ['project_id' => $id]);

            return response()->json(['success' => false, 'message' => 'Project not found.']);
        }

        if (empty($projectDetails->email)) {
            Log::error('Client email is empty or invalid', ['project_id' => $id]);

            return response()->json(['success' => false, 'message' => 'Client email is empty or invalid.']);
        }
        $select_document = EntryDocument::where('entry_process_model_id', $projectDetails->id)
            ->select('select_document')
            ->first();

        $project_req = null;

        if ($select_document && ! empty($select_document->select_document)) {
            $decoded = json_decode($select_document->select_document, true);

            // Convert array to comma-separated string for comparison
            if (is_array($decoded) && count($decoded) > 0) {
                $select_document = implode(',', $decoded);
            } else {
                $select_document = '';
            }

            switch ($select_document) {
                case 'sample_size':
                    $project_req = 'Sample Size';
                    break;
                case 'paper_statistics':
                    $project_req = 'Paper Statistics';
                    break;
                case 'thesis_statistics_with_text':
                    $project_req = 'Text Statistics with Text';
                    break;
                case 'thesis_statistics_without_text':
                    $project_req = 'Text Statistics without Text';
                    break;
                case 'writing':
                    $project_req = 'Writing';
                    break;
                case 'writing,with_statistics':
                case 'with_statistics,writing': // Add reverse if needed
                    $project_req = 'Writing with Statistics';
                    break;
                case 'writing,with_publication':
                    $project_req = 'Writing with Publication';
                    break;
                case 'writing,with_statistics,with_publication':
                    $project_req = 'Writing with Statistics and Publication';
                    break;
                case 'supporting_thesis_with_ms,supporting_thesis_without_ms':
                    $project_req = 'Supporting Thesis with MS and without MS';
                    break;
                case 'supporting_thesis_part1':
                    $project_req = 'Supporting Thesis Part 1';
                    break;
                case 'supporting_thesis_part2':
                    $project_req = 'Supporting Thesis Part 2';
                    break;
                case 'thesis_reviewing':
                    $project_req = 'Thesis Reviewing';
                    break;
                default:
                    $project_req = $select_document;
            }
        } else {
            $select_document = '';
        }

        // Get all payment info in one query
        $paymentStatus = PaymentStatusModel::with('paymentData')
            ->where('project_id', $projectDetails->id)
            ->first();

        // Declare default values
        $advanceAmount = '-';
        $partialAmount = '-';
        $finalAmount = '-';

        if ($paymentStatus && $paymentStatus->paymentData->isNotEmpty()) {
            $advancePending = $paymentStatus->paymentData->firstWhere('payment_type', 'advance_pending');
            $advanceReceived = $paymentStatus->paymentData->firstWhere('payment_type', 'advance_received');
            $paymentTypeCheck = collect($paymentStatus->paymentData)->first(function ($payment) {
                $type = is_array($payment) ? $payment['payment_type'] : $payment->payment_type;

                return in_array($type, ['advance_received', 'partial_payment_pending']);
            });

            if ($paymentTypeCheck) {
                $type = is_array($paymentTypeCheck) ? $paymentTypeCheck['payment_type'] : $paymentTypeCheck->payment_type;

                if ($type === 'advance_received') {
                    $paymentStatusText = 'Paid';
                } elseif ($type === 'partial_payment_pending') {
                    $paymentStatusText = 'Paid';
                }
            } else {
                $paymentStatusText = 'Not Paid';
            }

            $partialPending = $paymentStatus->paymentData->firstWhere('payment_type', 'partial_payment_pending');
            $partialPendingCheck = $paymentStatus->paymentData->firstWhere('payment_type', 'final_payment_pending');
            $partialReceived = $paymentStatus->paymentData->firstWhere('payment_type', 'partial_payment_received');
            if ($partialPendingCheck) {
                $paymentpartial = 'Paid';
            } else {
                $paymentpartial = 'Not Paid';
            }
            $finalPending = $paymentStatus->paymentData->firstWhere('payment_type', 'final_payment_pending');

            // $advanceAmount = $advancePending->payment ?? '';
            $advanceAmount = $advanceReceived->payment ?? $advancePending->payment ?? 0;
            Log::info('adv', ['value' => $advanceAmount]); // Corrected log format

            // $partialAmount = $partialPending->payment ?? '-';
            $partialAmount = $partialReceived->payment ?? $partialPending->payment ?? 0;

            $finalAmount = $finalPending->payment ?? '-';
        }

        $invoiceDetails = Setting::select('company_name', 'phone_number')->first();

        $process_type = null;
        if ($projectDetails && isset($projectDetails->process_status)) {
            switch ($projectDetails->process_status) {
                case 'in_progress':
                    $process_type = 'In Progress';
                    break;
                case 'completed':
                    $process_type = 'Completed';
                    break;
                case 'not_assigned':
                    $process_type = 'Not Assigned';
                    break;

                case 'client_review':
                    $process_type = 'Client Review';
                    break;

                case 'pending_author':
                    $process_type = 'Pending Author';
                    break;

                case 'withdrawal':
                    $process_type = 'Withdrawal';
                    break;

                default:
                    $process_type = ucfirst(str_replace('_', ' ', $projectDetails->process_status));
                    break;
            }
        }

        // Prepare email data
        $emailData = [
            'client_name' => $projectDetails->client_name,
            'type_of_work' => $projectDetails->type_of_work,
            'project_id' => $projectDetails->project_id,
            'project_requirement' => $projectDetails->title,
            'project_title' => $projectDetails->title,
            'project_status' => $projectDetails->project_status,
            'contact_number' => $projectDetails->contact_number,
            'budget' => $projectDetails->budget,
            'advance_payment' => $$advanceAmount ?? '',
            'advancePendingCheck' => $paymentStatusText,
            'partialPendingCheck' => $paymentpartial,
            'partial_payment' => $partialAmount ?? '-',
            'final_payment' => $finalAmount ?? '-',
            'name' => $invoiceDetails->company_name ?? '-',
            'phone_number' => $invoiceDetails->phone_number ?? '-',
            'process_status' => $process_type ?? '-',
            'pdf_path' => public_path('uploads/disclaimer.pdf'),
            'projectduration' => $projectDetails->projectduration ?? '-',

        ];

        Log::info('$emailData', $emailData);
        $requirementMap = [
            'sample_size' => SampleSizeMail::class,
            'paper_statistics' => PaperStatisticsMail::class,
            'thesis_statistics_with_text' => ThesisWithTextMail::class,
            'thesis_statistics_without_text' => ThesisWithoutTextMail::class,
            'writing' => WritingMail::class,
            'writing,with_statistics' => WritingWithStatisticsMail::class,
            'writing,with_publication' => WritingWithStatisticsMail::class,
            'writing,with_statistics,with_publication' => WritingWithStatisticsMail::class,
            'supporting_thesis_with_ms,supporting_thesis_without_ms' => ThesisWithMsMail::class,
            'supporting_thesis_part1' => ThesisWithMsMail::class,
            'supporting_thesis_part2' => ThesisWithMsMail::class,
            'thesis_reviewing' => ThesisReviewingMail::class,

        ];
        $entryEmailClass = $requirementMap[$select_document] ?? ClientEmail::class;

        try {
            $mailTypes = [
                'advance_payment' => AdvancePayment::class,
                'project_entry_email' => $entryEmailClass,
                'project_status_email' => ProjectStatusEmail::class,
                'project_payment_status_email' => ProjectPaymentStatusEmail::class,
                'partial_payment' => PartialPayment::class,
                'final_payment' => FinalPayment::class,
            ];

            if (! isset($mailTypes[$notificationV])) {
                Log::error('Invalid notification type', ['notification_v' => $notificationV]);

                return response()->json(['success' => false, 'message' => 'Invalid notification type.']);
            }

            // Check if record exists for the project_id and mail_type
            $status = MailNotification::where('project_id', $id)
                ->where('mail_type', $notificationV)
                ->first();

            if ($status) {
                // Update existing record
                $status->status = 'success';
                $status->created_by = $createdBy;
                $status->created_date = date('Y-m-d H:i:s');
                $status->save();
            } else {
                // Create a new record
                $status = new MailNotification;
                $status->project_id = $id;
                $status->mail_type = $notificationV;
                $status->status = 'success';
                $status->created_by = $createdBy;
                $status->created_date = date('Y-m-d H:i:s');
                $status->save();
            }

            // Send email
            Mail::to($projectDetails->email)->send(new $mailTypes[$notificationV]($emailData));
            Log::info('Mail sent successfully', [
                'project_id' => $id,
                'email' => $projectDetails->email,
                'notification_v' => $notificationV,
            ]);

            return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
        } catch (\Exception $e) {
            Log::error('Mail failed: '.$e->getMessage(), [
                'project_id' => $projectDetails->project_id,
                'email' => $projectDetails->email,
                'notification_v' => $notificationV,
            ]);

            // Update status if exists, otherwise create new failed entry
            if ($status) {
                $status->status = 'failed';
                $status->save();
            } else {
                $failedStatus = new MailNotification;
                $failedStatus->project_id = $id;
                $failedStatus->mail_type = $notificationV;
                $failedStatus->status = 'failed';
                $failedStatus->created_by = $createdBy;
                $failedStatus->created_date = date('Y-m-d H:i:s');
                $failedStatus->save();
            }

            return response()->json(['success' => false, 'message' => 'Failed to send email.']);
        }
    }

    public function showClientProjectNotification(Request $request, $id)
    {
        $notificationV = $request->query('notification_v');

        // Get project details by ID
        $projectDetails = EntryProcessModel::select(
            'id',
            'entry_date',
            'title',
            'project_id',
            'type_of_work',
            'email',
            'institute',
            'department',
            'profession',
            'budget',
            'process_status',
            'hierarchy_level',
            'created_by',
            'project_status',
            'assign_by',
            'assign_date',
            // DB::raw("CONCAT(DATEDIFF(projectduration, created_at), ' days ', MOD(TIMESTAMPDIFF(HOUR, created_at, projectduration), 24), ' hrs') AS projectduration"),
            DB::raw("CONCAT(DATEDIFF(projectduration, entry_date), ' days') AS projectduration"),
            'client_name',
            'contact_number'
        )
            // ->with(['documents'])
            ->find($id);

        if (! $projectDetails) {
            return response()->json(['success' => false, 'message' => 'Project not found.']);
        }
        $select_document = EntryDocument::where('entry_process_model_id', $projectDetails->id)
            ->select('select_document')
            ->first();

        $project_req = null;

        if ($select_document && ! empty($select_document->select_document)) {
            $decoded = json_decode($select_document->select_document, true);

            // Convert array to comma-separated string for comparison
            if (is_array($decoded) && count($decoded) > 0) {
                $select_document = implode(',', $decoded);
            } else {
                $select_document = '';
            }

            switch ($select_document) {
                case 'sample_size':
                    $project_req = 'Sample Size';
                    break;
                case 'paper_statistics':
                    $project_req = 'Paper Statistics';
                    break;
                case 'thesis_statistics_with_text':
                    $project_req = 'Text Statistics with Text';
                    break;
                case 'thesis_statistics_without_text':
                    $project_req = 'Text Statistics without Text';
                    break;
                case 'writing':
                    $project_req = 'Writing';
                    break;
                case 'writing,with_statistics':
                case 'with_statistics,writing': // Add reverse if needed
                    $project_req = 'Writing with Statistics';
                    break;
                case 'writing,with_publication':
                    $project_req = 'Writing with Publication';
                    break;
                case 'writing,with_statistics,with_publication':
                    $project_req = 'Writing with Statistics and Publication';
                    break;
                case 'supporting_thesis_with_ms,supporting_thesis_without_ms':
                    $project_req = 'Supporting Thesis with MS and without MS';
                    break;
                case 'supporting_thesis_part1':
                    $project_req = 'Supporting Thesis Part 1';
                    break;
                case 'supporting_thesis_part2':
                    $project_req = 'Supporting Thesis Part 2';
                    break;
                case 'thesis_reviewing':
                    $project_req = 'Thesis Reviewing';
                    break;
                default:
                    $project_req = $select_document;
            }
        } else {
            $select_document = '';
        }

        // Get all payment info in one query
        $paymentStatus = PaymentStatusModel::with('paymentData')
            ->where('project_id', $projectDetails->id)
            ->first();

        // Declare default values
        $advanceAmount = '-';
        $partialAmount = '-';
        $finalAmount = '-';

        if ($paymentStatus && $paymentStatus->paymentData->isNotEmpty()) {
            // $advancePending = $paymentStatus->paymentData->firstWhere('payment_type', 'advance_pending');
            // $advanceReceived = $paymentStatus->paymentData->firstWhere('payment_type', 'advance_received');
            $advancePending = $paymentStatus->paymentData->firstWhere('payment_type', 'advance_pending');
            $advanceReceived = $paymentStatus->paymentData->firstWhere('payment_type', 'advance_received');

            $paymentTypeCheck = collect($paymentStatus->paymentData)->first(function ($payment) {
                $type = is_array($payment) ? $payment['payment_type'] : $payment->payment_type;

                return in_array($type, ['advance_received', 'partial_payment_pending']);
            });

            if ($paymentTypeCheck) {
                $type = is_array($paymentTypeCheck) ? $paymentTypeCheck['payment_type'] : $paymentTypeCheck->payment_type;

                if ($type === 'advance_received') {
                    $paymentStatusText = 'Paid';
                } elseif ($type === 'partial_payment_pending') {
                    $paymentStatusText = 'Paid';
                }
            } else {
                $paymentStatusText = 'Not Paid';
            }

            $partialPending = $paymentStatus->paymentData->firstWhere('payment_type', 'partial_payment_pending');
            $partialReceived = $paymentStatus->paymentData->firstWhere('payment_type', 'partial_payment_received');
            $partialPendingCheck = $paymentStatus->paymentData->firstWhere('payment_type', 'final_payment_pending');

            if ($partialPendingCheck) {
                $paymentpartial = 'Paid';
            } else {
                $paymentpartial = 'Not Paid';
            }
            $finalPending = $paymentStatus->paymentData->firstWhere('payment_type', 'final_payment_pending');

            // $advanceAmount = $advancePending->payment ?? '';
            // $advanceAmount = ($advancePending->payment ?? 0) + ($advanceReceived->payment ?? 0);
            $advanceAmount = $advanceReceived->payment ?? $advancePending->payment ?? 0;

            // $partialAmount = $partialPending->payment ?? '-';
            // $partialAmount = ($partialPending->payment ?? 0) + ($partialReceived->payment ?? 0);
            $partialAmount = $partialReceived->payment ?? $partialPending->payment ?? 0;

            $finalAmount = $finalPending->payment ?? '-';
        }

        $invoiceDetails = Setting::select('company_name', 'phone_number')->first();

        // Process status mapping
        $process_type = null;
        if ($projectDetails && isset($projectDetails->process_status)) {
            switch ($projectDetails->process_status) {
                case 'in_progress':
                    $process_type = 'In Progress';
                    break;
                case 'completed':
                    $process_type = 'Completed';
                    break;
                case 'not_assigned':
                    $process_type = 'Not Assigned';
                    break;
                case 'client_review':
                    $process_type = 'Client Review';
                    break;
                case 'pending_author':
                    $process_type = 'Pending Author';
                    break;
                case 'withdrawal':
                    $process_type = 'Withdrawal';
                    break;
                default:
                    $process_type = ucfirst(str_replace('_', ' ', $projectDetails->process_status));
                    break;
            }
        }

        // Prepare email data
        $emailData = [
            'client_name' => $projectDetails->client_name,
            'type_of_work' => $projectDetails->type_of_work,
            'project_id' => $projectDetails->project_id,
            'project_title' => $projectDetails->title,
            'project_requirement' => $projectDetails->title,
            'project_status' => $projectDetails->project_status,
            'contact_number' => $projectDetails->contact_number,
            'budget' => $projectDetails->budget,
            'advance_payment' => $advanceAmount,
            'advancePendingCheck' => $paymentStatusText,
            'partial_payment' => $partialAmount,
            'partialPendingCheck' => $paymentpartial,
            'final_payment' => $finalAmount,
            'name' => $invoiceDetails->company_name ?? '-',
            'phone_number' => $invoiceDetails->phone_number ?? '-',
            'process_status' => $process_type ?? '-',
            'projectduration' => $projectDetails->projectduration ?? '-',
            'pdf_path' => public_path('uploads/disclaimer.pdf'),
        ];

        Log::info('Prepared email data', $emailData);
        $requirementMap = [
            'sample_size' => SampleSizeMail::class,
            'paper_statistics' => PaperStatisticsMail::class,
            'thesis_statistics_with_text' => ThesisWithTextMail::class,
            'thesis_statistics_without_text' => ThesisWithoutTextMail::class,
            'writing' => WritingMail::class,
            'writing,with_statistics' => WritingWithStatisticsMail::class,
            'writing,with_publication' => WritingWithStatisticsMail::class,
            'writing,with_statistics,with_publication' => WritingWithStatisticsMail::class,
            'supporting_thesis_with_ms,supporting_thesis_without_ms' => ThesisWithMsMail::class,
            'supporting_thesis_part1' => ThesisWithMsMail::class,
            'supporting_thesis_part2' => ThesisWithMsMail::class,
            'thesis_reviewing' => ThesisReviewingMail::class,

        ];
        $entryEmailClass = $requirementMap[$select_document] ?? ClientEmail::class;

        // Notification type mapping
        $mailTypes = [
            // 'project_entry_email'          => ClientEmail::class,
            'project_entry_email' => $entryEmailClass,
            'project_status_email' => ProjectStatusEmail::class,
            'project_payment_status_email' => ProjectPaymentStatusEmail::class,
            'advance_payment' => AdvancePayment::class,
            'partial_payment' => PartialPayment::class,
            'final_payment' => FinalPayment::class,
        ];

        if (! isset($mailTypes[$notificationV])) {
            return response()->json(['success' => false, 'message' => 'Invalid notification type.']);
        }

        // Try rendering the email
        try {
            $mailable = new $mailTypes[$notificationV]($emailData);
            $htmlContent = $mailable->render();

            return response($htmlContent, 200)
                ->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            Log::error('Email rendering failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'notification_type' => $notificationV,
                'email_data' => $emailData,
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to render email.']);
        }
    }

    public function getProjectList(Request $request)
    {
        $type = $request->query('type'); // writer, reviewer, or statistician
        $createdBy = $request->query('createdby'); // User ID
        $start_date = $request->query('start_date');
        $end_date = $request->query('end_date');
        $position = $request->query('position');
        $positions = explode(',', $position);
        $typeOfWork = $request->query('type_of_work', 'all'); // Default to 'all' if not provided
        $countdatatype = $request->query('count_type'); // writer, reviewer, or statistician
        // Base query for filtering projects
        $query = EntryProcessModel::select(
            'id',
            'entry_date',
            'title',
            'project_id',
            'type_of_work',
            'email',
            'institute',
            'department',
            'profession',
            'budget',
            'process_status',
            'hierarchy_level',
            'created_by',
            'project_status',
            'projectduration'
        )->where('is_deleted', 0);

        // Filter by type (writer, reviewer, or statistician)
        if ($type === 'writer') {
            $query->whereHas('writerData', function ($q) use ($createdBy) {
                $q->whereNotNull('assign_user')->where('assign_user', $createdBy)
                    ->whereIn('status', ['to_do', 'on_going', 'need_support', 'correction', 'plag_correction']);
            });
        } elseif ($type === 'reviewer') {
            $query->whereHas('reviewerData', function ($q) use ($createdBy) {
                $q->whereNotNull('assign_user')->where('assign_user', $createdBy)
                    ->whereIn('status', ['to_do', 'on_going', 'need_support', 'correction', 'plag_correction']);
            });
        } elseif ($type === 'statistician') {
            $query->whereHas('statisticianData', function ($q) use ($createdBy) {
                $q->whereNotNull('assign_user')->where('assign_user', $createdBy)
                    ->whereIn('status', ['to_do', 'on_going', 'need_support', 'correction', 'plag_correction']);
            });
        }

        // Apply additional filters for date range
        if (! empty($start_date) && ! empty($end_date)) {
            $query->whereBetween('entry_date', [$start_date, $end_date]);
        }
        // Filter by type of work, only if it is not 'all'
        if ($typeOfWork !== 'all') {
            $query->where('type_of_work', $typeOfWork);
        }

        // Fetch data
        $projects = $query->get();

        // Get the distinct types of work
        $typeofwork = EntryProcessModel::where('is_deleted', 0)
            ->groupBy('type_of_work')
            ->pluck('type_of_work');

        // Return response
        return response()->json([
            'details' => $projects,
            'typeofwork' => $typeofwork,
        ]);
    }

    public function getProjectEmpList(Request $request)
    {
        $countdatatype = $request->query('count_type');
        $created_by = $request->query('createdby');
        $position = $request->query('position');
        $start_date = $request->query('start_date');
        $end_date = $request->query('end_date');
        $typeOfWork = $request->query('type_of_work', 'all');
        $positions = explode(',', $position);

        $query = EntryProcessModel::with(['writerData', 'reviewerData', 'statisticanData'])
            ->where('is_deleted', 0)
            ->select(
                'id',
                'entry_date',
                'title',
                'project_id',
                'type_of_work',
                'email',
                'institute',
                'department',
                'profession',
                'budget',
                'process_status',
                'hierarchy_level',
                'created_by',
                'project_status',
                'assign_by',
                'assign_date',
                'projectduration'
            );

        if (! empty($countdatatype) && ! empty($countdatatype)) {
            $query->where('hierarchy_level', $countdatatype);
        }

        if (! empty($start_date) && ! empty($end_date)) {
            $query->whereBetween('entry_date', [$start_date, $end_date]);
        }
        // Filter by type of work, only if it is not 'all'
        if ($typeOfWork !== 'all') {
            $query->where('type_of_work', $typeOfWork);
        }

        // Use OR conditions to avoid filtering out entries incorrectly
        $query->where(function ($q) use ($positions, $created_by) {
            if (in_array('7', $positions)) {
                $q->orWhereHas('writerData', function ($subQuery) use ($created_by) {
                    $subQuery->where('assign_user', $created_by)
                        ->whereIn('status', ['to_do', 'on_going', 'need_support', 'correction', 'plag_correction']);
                });
            }

            if (in_array('8', $positions)) {
                $q->orWhereHas('reviewerData', function ($subQuery) use ($created_by) {
                    $subQuery->where('assign_user', $created_by)
                        ->whereIn('status', ['to_do', 'on_going', 'need_support', 'correction', 'plag_correction']);
                });
            }

            if (in_array('11', $positions)) {
                $q->orWhereHas('statisticanData', function ($subQuery) use ($created_by) {
                    $subQuery->where('assign_user', $created_by)
                        ->whereIn('status', ['to_do', 'on_going', 'need_support', 'correction', 'plag_correction']);
                });
            }
        });

        $projects = $query->get();

        // Get the distinct types of work
        $typeofwork = EntryProcessModel::where('is_deleted', 0)
            ->groupBy('type_of_work')
            ->pluck('type_of_work');

        // Return response
        return response()->json([
            'details' => $projects,
            'typeofwork' => $typeofwork,
        ]);
    }

    public function getEmpProjectList(Request $request)
    {
        $type = $request->input('type');
        $createdBy = $request->input('createdby');
        $typeOfWork = $request->input('type_of_work');
        $start_date = $request->query('start_date');
        $end_date = $request->query('end_date');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $positions = explode(',', $type);
        $types = [];

        if (in_array('7', $positions)) {
            $types[] = 'writer';
            $relations[] = 'writerData';
        }
        if (in_array('8', $positions)) {
            $types[] = 'reviewer';
            $relations[] = 'reviewerData';
        }
        if (in_array('11', $positions)) {
            $types[] = 'statistican';
            $relations[] = 'statisticanData';
        }

        // Now build the query
        $query = EntryProcessModel::with([
            'institute',
            'department',
            'profession',
            'paymentProcess',
            'employeePaymentDetails' => function ($query) use ($types) {
                $query->whereIn('type', $types);
            },
        ])
            ->select(
                'id',
                'project_id',
                'title',
                'type_of_work',
                'process_status',
                'hierarchy_level',
                'institute',
                'department',
                'profession',
                'client_name',
                'entry_date',
                'projectduration',
                DB::raw("CONCAT(DATEDIFF(projectduration, created_at), ' days ', MOD(TIMESTAMPDIFF(HOUR, created_at, projectduration), 24), ' hrs') AS projectduration")
            )
            ->where('is_deleted', 0)
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->where('process_status', '!=', 'completed');

        // Add dynamic whereHas filters
        $query->where(function ($q) use ($positions, $createdBy) {
            if (in_array('7', $positions)) {
                $q->orWhereHas('writerData', function ($subQuery) use ($createdBy) {
                    $subQuery->where('assign_user', $createdBy)
                        ->where('status', '!=', 'completed');
                });
            }

            if (in_array('8', $positions)) {
                $q->orWhereHas('reviewerData', function ($subQuery) use ($createdBy) {
                    $subQuery->where('assign_user', $createdBy)
                        ->where('status', '!=', 'completed');
                });
            }

            if (in_array('11', $positions)) {
                $q->orWhereHas('statisticanData', function ($subQuery) use ($createdBy) {
                    $subQuery->where('assign_user', $createdBy)
                        ->where('status', '!=', 'completed');
                });
            }
        });

        // Add relation eager loading only if needed
        if (! empty($relations)) {
            $query->with($relations);
        }

        if (! empty($typeOfWork) && $typeOfWork !== 'all') {
            $query->where('type_of_work', $typeOfWork);
        }

        if (! empty($start_date) && ! empty($end_date)) {
            $query->whereBetween('entry_date', [$start_date, $end_date]);
        }

        if (! empty($relations)) {
            $query->with($relations);
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            return response()->json([
                'details' => [],
                'typeofwork' => [],
                'projectsCount' => 0,
                'projectpendingcount' => 0,
                'projectemergencycount' => 0,
            ]);
        }

        $projectsCount = $query->count();
        $projectpendingcount = 0;
        $projectemergencycount = 0;

        foreach ($projects as $project) {
            $projectid = $project->id;
            $empid = $createdBy;

            if ($project->process_status !== 'completed') {
                $projectpendingcount++;
            }

            if ($project->hierarchy_level === 'urgent_important') {
                $projectemergencycount++;
            }

            // Initialize the properties with default values
            $project->completedIn4Days = '-';
            $project->completedIn5To8Days = '-';
            $project->completedInMoreThan8Days = '-';

            $assignList = ProjectAssignDetails::with('projectData')->where('project_id', $projectid)
                ->where('assign_user', $empid)
                ->whereHas('projectData', function ($q) use ($fromDate, $toDate) {
                    $q->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate);
                })
                ->first();

            if ($assignList) {
                // Check if the current user is assigned as writer, reviewer, or statistician
                if (in_array('7', $positions) || $assignList->assign_user === $createdBy) {
                    if ($project->writer_status === 'completed') {
                        $project->writer_pending_days = 0;
                    } else {
                        // Writer pending days calculation
                        $assignedDate = Carbon::parse($project->assign_date);
                        $projectDurationDate = Carbon::parse($project->project_duration);
                        $pendingDays = $assignedDate->diffInDays($projectDurationDate);

                        $project->writer_pending_days = $pendingDays;
                    }
                }

                if (in_array('8', $positions) || $assignList->assign_user === $createdBy) {
                    // $project->reviewer_pending_days = ($assignList->status === 'completed') ? 0
                    //     : Carbon::parse($assignList->assign_date)->diffInDays(Carbon::parse($assignList->project_duration));

                    if ($assignList->status === 'completed') {
                        $project->reviewer_pending_days = 0;
                    } else {
                        $assignedDate = Carbon::parse($assignList->assign_date);

                        $projectDurationDate = Carbon::parse($assignList->project_duration);

                        $pendingDays = $assignedDate->diffInDays($projectDurationDate);
                        $project->reviewer_pending_days = $pendingDays;
                    }
                }

                if (in_array('11', $positions) || $assignList->assign_user === $createdBy) {
                    // $project->statistican_pending_days = ($assignList->status === 'completed') ? 0
                    //     : Carbon::parse($assignList->assign_date)->diffInDays(Carbon::parse($assignList->project_duration));
                    if ($assignList->status === 'completed') {
                        $project->statistican_pending_days = 0;
                    } else {
                        $assignedDate = Carbon::parse($assignList->assign_date);

                        $projectDurationDate = Carbon::parse($assignList->project_duration);

                        $pendingDays = $assignedDate->diffInDays($projectDurationDate);
                        $project->statistican_pending_days = $pendingDays;
                    }
                }
            }

            $projectstatus = ProjectLogs::with('entryProcess')->where('project_id', $projectid)
                ->where('status', '!=', 'completed')
                ->where('employee_id', $empid)
                ->whereHas('entryProcess', function ($q) use ($fromDate, $toDate) {
                    $q->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate);
                })
                ->latest()
                ->first();

            // If a project log is found, calculate the date differences
            if ($projectstatus) {
                $statusDateTime = new \DateTime($projectstatus->assigned_date);
                $completedDateTime = new \DateTime($projectstatus->created_date);
                $interval = $statusDateTime->diff($completedDateTime);

                // Get the days difference, excluding the start date
                $daysDifference = $interval->days + 1;

                // Categorize based on days difference
                if ($daysDifference <= 4) {
                    $project->completedIn4Days = '<4 days';
                } elseif ($daysDifference >= 5 && $daysDifference <= 8) {
                    $project->completedIn5To8Days = '5 to 8 days';
                } elseif ($daysDifference >= 9) {
                    $project->completedInMoreThan8Days = '>9 days';
                }
            }
        }

        // Fetch distinct types of work
        $typeOfWorkOptions = EntryProcessModel::where('is_deleted', 0)
            ->select('type_of_work')
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->groupBy('type_of_work')
            ->get();

        // Return the response
        return response()->json([
            'details' => $projects,
            'typeofwork' => $typeOfWorkOptions,
            'projectsCount' => $projectsCount,
            'projectpendingcount' => $projectpendingcount,
            'projectemergencycount' => $projectemergencycount,

        ]);
    }

    public function calculatePendingDays($project, $createdBy)
    {
        if ($project->writer === $createdBy && ! empty($project->writer_assigned_date) && ! empty($project->writer_project_duration)) {
            $assignedDate = Carbon::parse($project->writer_assigned_date);
            $projectDurationDate = Carbon::parse($project->writer_project_duration);
            $project->writer_pending_days = $assignedDate->diffInDays($projectDurationDate);
        }

        if ($project->reviewer === $createdBy && ! empty($project->reviewer_assigned_date) && ! empty($project->reviewer_project_duration)) {
            $assignedDate = Carbon::parse($project->reviewer_assigned_date);
            $projectDurationDate = Carbon::parse($project->reviewer_project_duration);
            $project->reviewer_pending_days = $assignedDate->diffInDays($projectDurationDate);
        }

        if ($project->statistican === $createdBy && ! empty($project->statistican_assigned_date) && ! empty($project->statistican_project_duration)) {
            try {
                $assignedDate = Carbon::parse($project->statistican_assigned_date);
                $projectDurationDate = Carbon::parse($project->statistican_project_duration);
                $project->statistican_pending_days = $assignedDate->diffInDays($projectDurationDate);
            } catch (\Exception $e) {
                Log::error("Error parsing dates for project ID: {$project->id}", ['error' => $e->getMessage()]);
                $project->statistican_pending_days = 'Invalid date data';
            }
        }
    }

    //getting the upload the  csv file for the phpmyadmin
    public function uploadCsv(Request $request)
    {
        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $file->move(public_path('uploads'), $filename);

        $path = public_path('uploads/'.$filename);
        $data = array_map('str_getcsv', file($path));
        $csv_data = array_slice($data, 1);

        foreach ($csv_data as $key => $value) {
            $insert_data = [
                'Agent' => $value[0],
                'submission_date' => $value[1],
                'policy_status' => $value[2],
                'Eff_Date' => $value[3],
                'issuer' => $value[4],
                'state' => $value[5],
                'ffm_app_id' => $value[6],
                'first_name' => $value[7],
                'last_name' => $value[8],
                'PMPM' => $value[9],
                'Advance' => $value[10],
                'Members' => $value[11],
                'Advance_Excluded_Reason' => $value[12],
                'Post_Date' => $value[13],
                'created_by' => $value[14],
                'is_deleted' => $value[15],
                'created_at' => $value[16],
                'updated_at' => $value[17],
            ];

            EntryProcessModel::insert($insert_data);
        }

        return response()->json(['success' => true, 'message' => 'CSV file uploaded successfully.']);
    }

    public function getEmployeeProjectList(Request $request)
    {
        $type = $request->input('type');
        $createdBy = $request->input('createdby');
        $typeOfWork = $request->input('type_of_work');
        $start_date = $request->query('start_date');
        $end_date = $request->query('end_date');
        $currentYear = date('Y');
        $positions = explode(',', $type);
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        // Base query for filtering projects
        $query = EntryProcessModel::with(['instituteInfo', 'departmentInfo', 'professionInfo', 'paymentProcess', 'writerData', 'reviewerData', 'statisticanData', 'journalData', 'employeePaymentDetails'])
            ->select('id', 'project_id', 'title', 'department', 'institute', 'profession', 'type_of_work', 'process_status', 'assign_date', 'entry_date', 'hierarchy_level', 'client_name', 'projectduration')
            // ->whereRaw("DATE_FORMAT(entry_date, '%Y-%m') = ?", [$selectedMonth])
            ->where('is_deleted', 0);

        if (! empty($typeOfWork) && $typeOfWork !== 'all') {
            $query->where('type_of_work', $typeOfWork);
        }

        $query->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate);
        // $query->whereYear('entry_date', $currentYear);

        // Apply multiple position checks
        $query->where(function ($q) use ($positions, $createdBy) {
            if (in_array('7', $positions)) {
                $q->orWhereHas('writerData', function ($subQuery) use ($createdBy) {
                    $subQuery->where('assign_user', $createdBy);
                });
            }

            if (in_array('8', $positions)) {
                $q->orWhereHas('reviewerData', function ($subQuery) use ($createdBy) {
                    $subQuery->where('assign_user', $createdBy);
                });
            }

            if (in_array('11', $positions)) {
                $q->orWhereHas('statisticanData', function ($subQuery) use ($createdBy) {
                    $subQuery->where('assign_user', $createdBy);
                });
            }
        });

        // Filter by type of work
        if (! empty($typeOfWork) && $typeOfWork !== 'all') {
            $query->where('type_of_work', $typeOfWork);
        }

        // Filter by date range
        if (! empty($start_date) && ! empty($end_date)) {
            $query->whereBetween('entry_date', [$start_date, $end_date]);
        }

        // Fetch the projects count
        $projects = $query->get();

        foreach ($projects as $project) {
            $projectid = $project->id;
            $empid = $createdBy;

            // Initialize the properties with default values
            $project->completedIn4Days = '-';
            $project->completedIn5To8Days = '-';
            $project->completedInMoreThan8Days = '-';

            $projectstatus1 = ProjectLogs::with('entryProcess')->where('project_id', $projectid)
                ->where('employee_id', $empid)
                ->whereHas('entryProcess', function ($q) use ($fromDate, $toDate) {
                    $q->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate);
                })
                ->first();

            // $project->assigned_date = $projectstatus1->assigned_date;
            // Fetch the latest completed project log

            $projectstatus = ProjectLogs::with('entryProcess')->where('project_id', $projectid)
                ->where('status', '!=', 'completed')
                ->where('employee_id', $empid)
                ->whereHas('entryProcess', function ($q) use ($fromDate, $toDate) {
                    $q->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate);
                })
                ->latest()
                ->first();

            if ($projectstatus) {
                // Calculate the difference in days
                $statusDateTime = new \DateTime($projectstatus->assigned_date);
                $completedDateTime = new \DateTime($projectstatus->created_date);
                // Calculate the difference between the dates
                $interval = $statusDateTime->diff($completedDateTime);

                // Get the days difference, excluding the start date
                $daysDifference = $interval->days + 1;
                // Categorize based on day difference
                if ($daysDifference <= 4) {
                    $project->completedIn4Days = '<4 days';
                } elseif ($daysDifference >= 5 && $daysDifference <= 8) {
                    $project->completedIn5To8Days = '5 to 8 days';
                } elseif ($daysDifference >= 9) {
                    $project->completedInMoreThan8Days = '>9 days';
                }
            } else {
                $project->completedIn4Days = '-';
                $project->completedIn5To8Days = '-';
                $project->completedInMoreThan8Days = '-';
            }
        }

        // Fetch distinct types of work
        $typeOfWorkOptions = EntryProcessModel::where('is_deleted', 0)
            ->select('type_of_work')
            ->whereDate('entry_date', '>=', $fromDate)
            ->whereDate('entry_date', '<=', $toDate)
            ->groupBy('type_of_work')
            ->get();

        // Return response
        return response()->json([
            'details' => $projects,
            'typeofwork' => $typeOfWorkOptions,
        ]);
    }

    public function getProjectView(Request $request)
    {
        $type = $request->type;
        $process_status = $request->process_status;
        $journal_status = $request->journal_status;
        $completed_count = $request->completed_count;
        $count_type = $request->count_type;
        $created_by = $request->created_by;
        $currentYear = date('Y');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $client_review = $request->client_review;
        $listview = $request->listview;

        if ($listview === 'sme') {
            $totalProjectsQuery = EntryProcessModel::pluck('id')
                ->with(['institute', 'department', 'profession'])
                ->whereDate('entry_date', '>=', $fromDate)
                ->whereDate('entry_date', '<=', $toDate)
                ->toArray();
            // Fetch completed projects with eager loading
            $projectcompletedQuery = ProjectAssignDetails::with('projectData')
                ->whereIn('project_id', $totalProjectsQuery)
                ->whereIn('type', ['reviewer', 'statistican'])
                ->where('status', 'completed')
                ->get();

            // If a count_type is provided, filter by hierarchy_level in projectData
            if (! empty($count_type)) {
                $projectcompletedQuery->whereHas('projectData', function ($query) use ($count_type) {
                    $query->where('hierarchy_level', $count_type);
                });
            }

            // Get the results and ensure unique project_id
            $projectcompleted = $projectcompletedQuery->get()->unique('project_id');

            $formattedProjects = $projectcompleted->map(function ($projectAssignDetail) {
                $projectData = $projectAssignDetail->projectData;
                $writerStatus = $projectData->writerData && $projectData->writerData->isNotEmpty()
                    ? $projectData->writerData->pluck('status')->implode(', ')
                    : '-';

                $reviewerStatus = $projectData->reviewerData && $projectData->reviewerData->isNotEmpty()
                    ? $projectData->reviewerData->pluck('status')->implode(', ')
                    : '-';

                $statisticanStatus = $projectData->statisticanData && $projectData->statisticanData->isNotEmpty()
                    ? $projectData->statisticanData->pluck('status')->implode(', ')
                    : '-';

                $journalStatus = $projectData->journalData->pluck('status')->implode(', ') ?? '-';

                return [
                    'id' => $projectAssignDetail->project_id,
                    'type_of_work' => $projectData->type_of_work ?? '',

                    'writer_status' => $writerStatus,
                    'reviewer_status' => $reviewerStatus,
                    'statistican_status' => $statisticanStatus,
                    'journal_status' => $journalStatus,
                    'process_status' => $projectData->process_status ?? '-',
                    'payment_status' => $projectAssignDetail->payment_process->payment_status ?? '-',
                    'duration_diff' => $projectAssignDetail->duration_diff ?? '-',
                ];
            });

            return response()->json([
                'details' => $formattedProjects,
                'message' => 'Projects retrieved successfully',
            ]);
        } else {
            $totalProjectsQuery = EntryProcessModel::with(['paymentProcess:id,project_id,payment_status', 'writerData', 'reviewerData', 'statisticanData', 'journalData', 'instituteInfo', 'departmentInfo', 'professionInfo'])
                ->select(
                    'id',
                    'project_id',
                    'type_of_work',
                    'title',
                    'process_status',
                    'entry_date',
                    'hierarchy_level',
                    'institute',
                    'department',
                    'profession',
                    'client_name',
                    DB::raw("CONCAT(DATEDIFF(projectduration, created_at), ' days ', MOD(TIMESTAMPDIFF(HOUR, created_at, projectduration), 24), ' hrs') AS projectduration"),
                )
                // ->whereYear('entry_date', $currentYear)
                ->whereDate('entry_date', '>=', $fromDate)
                ->whereDate('entry_date', '<=', $toDate)

                ->where('is_deleted', 0);

            if (! empty($type)) {
                $totalProjectsQuery->where('type_of_work', $type);
            }

            // Check the status of 'writerData' and only pluck the necessary field
            $totalProjectsQuery->with(['writerData' => function ($query) {
                $query->pluck('status'); // Plucking the status from the 'writerData' relationship
            }]);

            if (! empty($process_status)) {
                $totalProjectsQuery->where('process_status', $process_status);
            }

            if (! empty($journal_status)) {
                $totalProjectsQuery->whereHas('journalData', function ($query) use ($journal_status) {
                    if ($journal_status === 'submitted') {
                        // Include both 'submitted' and 'peer_review'
                        $query->whereIn('status', ['submitted', 'peer_review']);
                    } elseif ($journal_status === 'resubmission') {
                        $query->whereIn('status', ['resubmission', 'rejected']);
                    } else {
                        // Normal filtering
                        $query->whereIn('status', explode(',', $journal_status));
                    }
                });
            }

            if (! empty($completed_count)) {
                $totalProjectsQuery->where('process_status', '=', 'completed')
                    ->where('type_of_work', $completed_count);
            }

            if (! empty($count_type)) {
                if ($count_type === 'emergency_work') {
                    if ($created_by) {
                        $totalProjectsQuery = $totalProjectsQuery->where('hierarchy_level', 'urgent_important')->where('process_status', '!=', 'completed');
                    } else {
                        $totalProjectsQuery = $totalProjectsQuery->where('hierarchy_level', 'urgent_important')->where('process_status', '!=', 'completed');
                    }
                } elseif ($count_type === 'not_assigned_work') {
                    $totalProjectsQuery = $totalProjectsQuery->where('process_status', 'not_assigned');
                }
            }

            $totalProjects = $totalProjectsQuery->get()->map(function ($project) {
                // Convert writer status to comma-separated string
                $project->writer_status = $project->writerData->isNotEmpty()
                    ? $project->writerData->pluck('status')->implode(', ')
                    : null;

                // Convert reviewer status to comma-separated string
                $project->reviewer_status = $project->reviewerData->isNotEmpty()
                    ? $project->reviewerData->pluck('status')->implode(', ')
                    : null;

                // Convert statistician status to comma-separated string
                $project->statistican_status = $project->statisticanData->isNotEmpty()
                    ? $project->statisticanData->pluck('status')->implode(', ')
                    : null;

                // Remove unnecessary relationships from the result
                unset($project->writerData, $project->reviewerData, $project->statisticanData);

                return $project;
            });

            // Handle special count type logic
            $delayedProjects = [];
            $projectDelayCount = 0;
            $currentDate = now()->format('Y-m-d');

            if (! empty($count_type)) {
                if ($count_type === 'project_delay') {
                    $projectdelayDataList = EntryProcessModel::with(['paymentProcess', 'instituteInfo', 'departmentInfo', 'professionInfo'])->select(
                        'id',
                        'title',
                        'institute',
                        'department',
                        'entry_date',
                        'profession',
                        'client_name',
                        DB::raw("CONCAT(DATEDIFF(projectduration, created_at), ' days ', MOD(TIMESTAMPDIFF(HOUR, created_at, projectduration), 24), ' hrs') AS projectduration"),
                        'hierarchy_level',
                        'project_id'
                    )
                        ->where('projectduration', '<', $currentDate)
                        ->where('is_deleted', 0)
                        ->where('process_status', '!=', 'completed')
                        // ->whereYear('entry_date', $currentYear)
                        ->whereDate('entry_date', '>=', $fromDate)
                        ->whereDate('entry_date', '<=', $toDate)

                        ->orderBy('id', 'desc')
                        ->get()
                        ->map(function ($items) {
                            return [
                                'id' => $items->id,
                                'project_id' => $items->project_id,
                                'title' => $items->title,
                                'projectDuration' => $items->projectduration,
                                'institute' => $items->instituteInfo->name,
                                'department' => $items->departmentInfo->name,
                                'profession' => $items->professionInfo->name,
                                'entry_date' => $items->entry_date,
                                'payment_status' => $items->paymentProcess->payment_status ?? '-',
                                'client_name' => $items->client_name,
                            ];
                        });

                    return response()->json([
                        'delayed_projects' => $projectdelayDataList,
                        // 'freelancer_details' => $freelancers
                    ]);
                } elseif ($count_type === 'freelancer_count') {
                    $freelancerPaymentCount = 0;
                    $freelancers = [];
                    $assignprojectIds = [];
                    $addedProjectIds = []; // To track unique project IDs

                    foreach ($totalProjects as $entry) {
                        $assignproject = ProjectAssignDetails::where('project_id', $entry->id)
                            ->pluck('assign_user')->toArray();

                        if (! empty($assignproject)) {
                            $assignprojectIds[$entry->id] = array_unique($assignproject);
                        }
                    }

                    $allAssignUserIds = array_unique(array_merge(...array_values($assignprojectIds)));

                    if (! empty($allAssignUserIds)) {
                        // Fetch freelancers only once
                        $userhrms = DB::connection('mysql_medics_hrms')
                            ->table('employee_details')
                            ->where('employee_type', 'freelancers')
                            ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                            ->whereIn('id', $allAssignUserIds)
                            ->where('status', '1')
                            ->get();

                        // Count unique freelancers
                        $freelancerPaymentCount = $userhrms->unique('id')->count();

                        // Map freelancers by ID for easier lookup
                        $freelancersById = $userhrms->keyBy('id');

                        // Assign freelancers to their respective projects
                        foreach ($totalProjects as $entry) {
                            if (! isset($assignprojectIds[$entry->id])) {
                                continue;
                            }

                            // If this project ID has already been added, skip it
                            if (in_array($entry->id, $addedProjectIds)) {
                                continue;
                            }

                            foreach ($assignprojectIds[$entry->id] as $freelancerId) {
                                if (isset($freelancersById[$freelancerId])) {
                                    $user = $freelancersById[$freelancerId];
                                    $freelancers[] = [
                                        'name' => $user->employee_name,
                                        'employee_type' => $user->employee_type,
                                        'email' => $user->email_address,
                                        'project_id' => $entry->project_id,
                                        'id' => $entry->id,
                                        'entry_date' => $entry->entry_date,
                                        'hierarchy_level' => $entry->hierarchy_level,
                                        'type_of_work' => $entry->type_of_work,
                                        'title' => $entry->title,
                                        'process_status' => $entry->process_status,
                                        'writer' => $entry->writer,
                                        'reviewer' => $entry->reviewer,
                                        'statistican' => $entry->statistican,
                                        'journal' => $entry->journal,
                                        'writer_status' => $entry->writer_status,
                                        'reviewer_status' => $entry->reviewer_status,
                                        'statistican_status' => $entry->statistican_status,
                                        'journal_status' => $entry->journal_status,
                                        'client_name' => $entry->client_name,
                                        'project_duration' => $entry->projectduration,
                                        'payment_status' => $entry->paymentProcess->payment_status ?? '-',
                                        'institute' => $entry->instituteInfo ?? '-',
                                        'department' => $entry->departmentInfo ?? '-',
                                        'profession' => $entry->professionInfo ?? '-',
                                    ];

                                    //Mark this project as added and break the loop for unique project
                                    $addedProjectIds[] = $entry->id;
                                    break; // No need to add more freelancers for the same project
                                }
                            }
                        }
                    }

                    // Get the final count
                    $freelancerPaymentCounts = count($freelancers);

                    return response()->json([
                        'freelancer_count' => $freelancerPaymentCounts,
                        'freelancer_details' => $freelancers,
                    ]);
                }
            }
        }

        return response()->json([
            'details' => $totalProjects,
        ]);
    }

    public function getEmpPayment(Request $request)
    {
        $payment_status = $request->payment_status;
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        // Fetch projects with related data
        $totalProjects = PaymentStatusModel::with(['projectData.writerData', 'projectData.reviewerData', 'projectData.statisticanData', 'projectData'])
            ->where('payment_status', $payment_status)
            ->whereHas('projectData', function ($query) use ($fromDate, $toDate) {
                $query->where('is_deleted', 0)
                    ->whereDate('entry_date', '>=', $fromDate)
                    ->whereDate('entry_date', '<=', $toDate);
            })
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'project_name' => $project->projectData->title ?? null, // Ensure title exists
                    'payment_status' => $project->payment_status,
                    'writer_status' => optional($project->projectData->writerData)->isNotEmpty()
                        ? $project->projectData->writerData->pluck('status')->implode(', ')
                        : null,
                    'reviewer_status' => optional($project->projectData->reviewerData)->isNotEmpty()
                        ? $project->projectData->reviewerData->pluck('status')->implode(', ')
                        : null,
                    'statistican_status' => optional($project->projectData->statisticanData)->isNotEmpty()
                        ? $project->projectData->statisticanData->pluck('status')->implode(', ')
                        : null,
                    'project_data' => $project->projectData,
                ];
            });

        return response()->json([
            'details' => $totalProjects,
        ]);
    }

    public function getEmpPayments(Request $request)
    {
        $payment_status = $request->payment_status;
        // $fromDate = $request->query('from_date');
        // $toDate = $request->query('to_date');
        // Fetch projects with related data
        $totalProjects = PaymentStatusModel::with(['projectData.writerData', 'projectData.reviewerData', 'projectData.statisticanData', 'projectData'])
            ->where('payment_status', $payment_status)
            ->whereHas('projectData', function ($query) {
                $query->where('is_deleted', 0);

            })
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'project_name' => $project->projectData->title ?? null, // Ensure title exists
                    'payment_status' => $project->payment_status,
                    'writer_status' => optional($project->projectData->writerData)->isNotEmpty()
                        ? $project->projectData->writerData->pluck('status')->implode(', ')
                        : null,
                    'reviewer_status' => optional($project->projectData->reviewerData)->isNotEmpty()
                        ? $project->projectData->reviewerData->pluck('status')->implode(', ')
                        : null,
                    'statistican_status' => optional($project->projectData->statisticanData)->isNotEmpty()
                        ? $project->projectData->statisticanData->pluck('status')->implode(', ')
                        : null,
                    'project_data' => $project->projectData,
                ];
            });

        return response()->json([
            'details' => $totalProjects,
        ]);
    }

    public function projectIncome(Request $request)
    {
        $selectedMonth = $request->input('month');
        $selectedYear = explode('-', $selectedMonth)[0];

        $typeofwork = [
            'presentation' => 0,
            'manuscript' => 0,
            'statistics' => 0,
            'thesis' => 0,
            'others' => 0,
            // 'Total' => 0
        ];

        $monthlyPayments = PaymentStatusModel::with(['paymentData', 'projectData', 'paymentLog'])
            ->whereHas('paymentLog', function ($query) use ($selectedMonth) {
                $query->whereRaw("DATE_FORMAT(created_date, '%Y-%m') = ?", [$selectedMonth])
                    ->where('payment_status', 'completed');
            })
            ->get();

        $yearlyPayments = PaymentStatusModel::with(['paymentData', 'projectData', 'paymentLog'])
            ->whereHas('paymentLog', function ($query) use ($selectedYear) {
                $query->whereRaw('YEAR(created_date) = ?', [$selectedYear])
                    ->where('payment_status', 'completed');
            })
            ->get();

        $monthlyIncomeData = $monthlyPayments->groupBy('projectData.type_of_work')->mapWithKeys(function ($group, $key) {
            return [$key => $group->sum(fn ($item) => optional($item->projectData)->budget ?? 0)];
        })->toArray();

        $finalMonthlyIncome = array_merge($typeofwork, $monthlyIncomeData);

        $totalMonthlyValue = array_sum($finalMonthlyIncome);
        // $finalMonthlyIncome['Total'] = $totalMonthlyValue;

        $monthlyIncomeWithPercentage = collect($finalMonthlyIncome)->map(function ($value, $name) use ($totalMonthlyValue) {
            return [
                'name' => $name,
                'value' => $value,
                'percentage' => $totalMonthlyValue > 0 ? round(($value / $totalMonthlyValue) * 100, 2) : 0,
            ];
        })->values();

        $yearlyIncomeData = $yearlyPayments->groupBy('projectData.type_of_work')->mapWithKeys(function ($group, $key) {
            return [$key => $group->sum(fn ($item) => optional($item->projectData)->budget ?? 0)];
        })->toArray();

        $finalYearlyIncome = array_merge($typeofwork, $yearlyIncomeData);

        $totalYearlyValue = array_sum($finalYearlyIncome);
        // $finalYearlyIncome['Total'] = $totalYearlyValue;

        $yearlyIncomeWithPercentage = collect($finalYearlyIncome)->map(function ($value, $name) use ($totalYearlyValue) {
            return [
                'name' => $name,
                'value' => $value,
                'percentage' => $totalYearlyValue > 0 ? round(($value / $totalYearlyValue) * 100, 2) : 0,
            ];
        })->values();

        return response()->json([
            'monthly_income' => $monthlyIncomeWithPercentage,
            'yearly_income' => $yearlyIncomeWithPercentage,
        ]);
    }

    public function getProjectDeleteList(Request $request)
    {
        $deletedList = EntryProcessModel::where('is_deleted', '1')->get();

        return response()->json([
            'details' => $deletedList,
        ]);
    }

    public function getProjectStatusChange(Request $request, $id)
    {
        $details = EntryProcessModel::find($id);
        $details->is_deleted = 0;
        $details->save();

        return response()->json($details);
    }

    public function getSupporStatusChange(Request $request, $id)
    {
        //      status: statusValue,
        //   type: statusType,
        //   assign_user_type: statusType,
        //   createdby: createdby,
        //   project_id: id_p,
        //   p_emdid: p_emdid,
        //   assign_user: assign_user,
        //   projectid: projectId,
        //   assigned_date: assign_date,
        //   status_date: status_date,
        $status = $request->input('status');
        $type = $request->input('type');
        $createdby = $request->input('createdby');
        $emp_id = $request->input('p_emdid');
        $project_id = $request->input('projectid');
        $project_number = $request->input('project_id');
        $assign_user = $request->input('assign_user');
        $assign_user_type = $request->input('assign_user_type');
        $assigned_date = $request->input('assigned_date');
        $status_date = $request->input('status_date');
        $current_date_formatted = (new \DateTime)->format('Y-m-d');
        $delete = $request->input('delete');
        $type_sme = $request->input('type_sme');

        Log::info('Attempting to update status for project', [
            'project_id' => $id,
            'status' => $status,
            'type' => $type,
            'emp_id' => $emp_id,
            'created_by' => $createdby,
            'request_data' => $request->all(),
        ]);

        if ($type === 'publication_manager') {
            $details = ProjectAssignDetails::where('project_id', $id)
                // ->where('assign_user', $assign_user)
                ->where('type', $type)->latest()->first();

            // $project = ProjectAssignDetails::where('project_id', $id)
            // ->where('id', $emp_id)
            // ->where('type', $type)->latest()->first();

            if ($details) {
                $details->status = $status;
                $details->created_by = $createdby;
                $details->updated_at = now();
                $details->save();
            } else {
                $details = new ProjectAssignDetails;
                $details->project_id = $id;
                $details->assign_user = '';
                // $details->assign_user = $assign_user ?? $project_number;
                $details->assign_date = $current_date_formatted;
                $details->status = $status;
                $details->status_date = $current_date_formatted;
                $details->project_duration = $current_date_formatted;
                $details->type = $type;
                $details->created_by = $createdby;
                $details->save();
            }

            $projectEntry = EntryProcessModel::where('id', $id)
                ->whereIn('process_status', ['client_review', 'pending_author'])
                ->first();

            if ($projectEntry) {
                $projectEntry->process_status = 'in_progress';
                $projectEntry->save();
            }

            $userDetails = User::find($id);

            $activity = new ProjectActivity;
            $activity->project_id = $id;
            $activity->activity = 'SME marked as  '.$status.' to Publication Manager';
            $activity->role = 'Sme';
            $activity->created_by = $createdby;
            $activity->created_date = now();
            $activity->save();

            $positions = [13, 14, 'Admin'];

            $users = User::whereIn('position', $positions)
                ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                ->get()
                ->keyBy('position');

            $emails = [
                'projectManager' => $users->get(13)?->email_address,
                'teamManager' => $users->get(14)?->email_address,
                'publicationManager' => $users->get(27)?->email_address,
                'adminEmail' => $users->get('Admin')?->email_address,

            ];
            $publication = User::with(['createdByUser'])->where('position', '27')->first();
            $assignTo = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $publication->id)->first();

            $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $createdby)->first();
            // $createdDetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $createdby)->first();
            $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

            if (! empty($emails['projectManager']) && ! empty($emails['teamManager']) && ! empty($emails['adminEmail'])) {
                try {
                    // Send email to writer with CC to others
                    Mail::to($emails['projectManager'], $emails['teamManager'])
                        ->cc($emails['adminEmail'])
                        ->send(new CorrectionNotificationMail([
                            'projectManagerEmail' => $emails['projectManager'],
                            'teamManagerEmail' => $emails['teamManager'],
                            'adminEmail' => $emails['adminEmail'],
                            // 'publicationManagerEmail' => $emails['publicationManager'],
                            // 'writer_status' => $project->process_status,
                            // 'employee_name' => $employeedetails->employee_name,
                            'employee_name' => $assignTo->employee_name,
                            'assign_to' => $assignTo->employee_name,
                            'role' => $employeedetails->createdByUser?->name,
                            'phone_number' => $employeedetails->phone_number,
                            'project_id' => $projectDetails->project_id,
                            'type' => $type,
                            'status' => $status,
                            'createdBy' => $employeedetails->employee_name,
                            // 'detail_name' => $request->status
                        ]));

                    Log::info('Email sent to project manager and team manager');
                } catch (\Exception $e) {
                    Log::error('Mail failed: '.$e->getMessage());
                }
            } else {
                Log::error('One or more email addresses are missing.');
            }

            $projectLog = new ProjectLogs;
            $projectLog->project_id = $id;
            // $projectLog->employee_id = $assign_user ?? $project_number;
            $projectLog->employee_id = '';
            $projectLog->assigned_date = $current_date_formatted;
            $projectLog->status = $status;
            $projectLog->status_date = $current_date_formatted;
            $projectLog->status_type = $type;
            $projectLog->created_date = $current_date_formatted;
            $projectLog->created_by = $createdby;
            $projectLog->assing_preview_id = optional(ProjectLogs::where('project_id', $id)->where('status_type', $assign_user_type)->latest()->first())->id;
            $projectLog->save();
        }

        if ($type === 'payment') {
            $details = PaymentStatusModel::where('project_id', $project_number)->orderBy('id', 'desc')->first();
            if ($details) {
                $details->payment_status = $status;
                $details->created_by = $createdby;
                $details->updated_at = now();
                $details->save();
            }

            if ($details) {
                $paymentDetails = PaymentDetails::where('payment_id', $details->id)->orderBy('id', 'desc')->first();
                if ($paymentDetails) {
                    $paymentDetails->payment_type = $status;
                    $paymentDetails->updated_at = now();
                    $paymentDetails->save();
                }
            }

            if ($details) {
                $paymentLogs = PaymentLogs::where('project_id', $project_number)
                    ->where('payment_id', $details->id)
                    ->orderBy('id', 'desc')->first();
                if ($paymentLogs) {
                    $paymentLogs->payment_status = $status;
                    $paymentLogs->created_by = $createdby;
                    $paymentLogs->updated_at = now();
                    $paymentLogs->save();
                }
            }
        }
        if ($type === 'commonStatus') {
            $details = EntryProcessModel::where('project_id', $project_id)->first();

            if ($details) {
                $details->process_status = $status;
                $details->save();

                if ($status) {

                    $exists = ProjectActivity::where('project_id', $project_id)
                        ->where('activity', 'project process status  marked as '.$status)
                        ->exists();

                    if (! $exists) {
                        $activity = new ProjectActivity;
                        $activity->project_id = $project_number;
                        $activity->activity = 'project process status  marked as '.$status.' By SME';
                        $activity->role = 'SME';
                        $activity->created_by = $createdby;
                        $activity->created_date = now();
                        $activity->save();
                    }
                }

                // $status_view = ProjectViewStatus::where('project_id', $project_id)->latest()->first();

                // if ($status_view) {
                //     $status_view->project_status = $status;
                //     $status_view->save();
                // } else {
                //     ProjectViewStatus::create([
                //     'project_id' => $project_id,
                //     'project_status' => $status,
                //     'created_by' => $createdby,
                //     'created_date' => now(),
                //     ]);
                // }

                $projectView = new ProjectViewStatus;
                $projectView->project_id = $project_number;
                $projectView->project_status = $status;
                $projectView->created_by = $createdby;
                $projectView->created_date = now();
                $projectView->save();

                Log::info('Process status updated:', [
                    'project_id' => $project_id,
                    'status' => $status,
                ]);
            } else {
                Log::error('EntryProcessModel not found for project_id:', [
                    'project_id' => $project_id,
                ]);
            }
        }

        if ($type === 'sme') {
            $details = EntryProcessModel::where('project_id', $project_id)->first();
        }

        // Find the project and update it
        $project = ProjectAssignDetails::where('project_id', $id)
            ->where('id', $emp_id)
            ->where('type', $type)
            ->latest()->first();

        if ($project) {
            $project->created_by = $createdby;
            $project->status = $status;
            $project->save();

            $projectEntry = EntryProcessModel::where('id', $id)
                ->whereIn('process_status', ['client_review', 'pending_author'])
                ->first();

            if ($projectEntry) {
                $projectEntry->process_status = 'in_progress';
                $projectEntry->save();
            }

            // $userDetails = User::with(['createdByUser'])->find($assign_user);
            // $created = User::with(['createdByUser'])->find($createdby);
            //     $employee = $created?->employee_name ?? '';
            //     $creator  = $created?->createdByUser?->name ?? '';

            // $activity = new ProjectActivity;
            // $activity->project_id = $id;
            // // $activity->activity = $status.' assigned to '.$userDetails->employee_name;
            // $activity->activity = $status
            //     .' assigned to '
            //     .($userDetails?->employee_name ?? 'publication manager'). ' by '.$creator.'('. $employee.')';

            // $activity->created_by = $createdby;
            // $activity->created_date = now();
            // $activity->save();

            $userDetails = User::with(['createdByUser'])->find($assign_user);
            // if ($userDetails->position === '7,8') {
            //     $created = 'Writer, Reviewer';
            // } else {
            $created = User::with(['createdByUser'])->find($createdby);
            // }

            Log::info('created', ['created' => $created]);
            $employee = $created?->employee_name ?? null;
            $creator = $created?->createdByUser?->name ?? null;
            Log::info('crr', ['creator' => $creator]);

            $activity = new ProjectActivity;
            $activity->project_id = $id;

            // Build "by Creator (Employee)" part cleanly
            $byText = '';
            if ($creator && $employee) {
                $byText = " by $creator ($employee)";
            } elseif ($creator) {
                $byText = " by $creator";
            } elseif ($employee) {
                $byText = " by $employee";
            }

            // Build final activity text
            $assignedTo = $userDetails?->employee_name ?? 'publication manager';

            $activity->activity = "{$status} assigned to {$assignedTo}{$byText}";
            if ($creator === null) {

                if ($type === 'writer') {
                    $activity->role = 'reviewer';
                } elseif ($type === 'reviewer') {
                    $activity->role = 'statistician';
                }
            } else {
                $activity->role = $creator;
            }

            $activity->created_by = $createdby;
            $activity->created_date = now();
            $activity->save();

            $correction_tc = ProjectAssignDetails::where('project_id', $id)
                ->where('type', 'team_coordinator')
                // ->where('status', 'correction')
                ->orderBy('id', 'desc')
                ->first();

            if ($correction_tc) {
                $correction_tc->project_id = $id;
                $correction_tc->assign_user = $createdby;
                $correction_tc->status = $status;
                $correction_tc->created_by = $createdby;
                $correction_tc->updated_at = now();
                $correction_tc->save();

            } else {

                ProjectAssignDetails::create([

                    'project_id' => $id,
                    'assign_user' => $createdby,
                    'status' => $status,
                    'type' => 'team_coordinator',
                    // 'type_sme' => 'abc',
                    'created_by' => $createdby,
                    'is_deleted' => $delete,
                ]);
            }

            $checkproject_log = ProjectLogs::where('project_id', $id)->where('employee_id', $assign_user)->where('status_type', $assign_user_type)->latest()->first();

            //project logs
            ProjectLogs::create([
                'project_id' => $id,
                'employee_id' => $assign_user,
                'assigned_date' => $assigned_date,
                'status' => $status,
                'status_date' => $status_date,
                'status_type' => $type,
                // 'assing_preview_id' => $checkproject_log->id,
                'assing_preview_id' => optional(ProjectLogs::where('project_id', $id)->where('employee_id', $assign_user)->where('status_type', $assign_user_type)->latest()->first())->id,
                'created_by' => $createdby,
                'created_date' => date('Y-m-d H:i:s'),
            ]);

            // $userDetails = User::where("id", $assign_user)->first();

            $positions = [13, 14, 'Admin'];

            $users = User::whereIn('position', $positions)
                ->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')
                ->get()
                ->keyBy('position');

            $emails = [
                // 'projectManager' => "barathkrishnamoorthy17@gmail.com",
                // 'teamManager' => "barathkrishnamoorthy17@gmail.com",

                // 'adminEmail' =>"barathkrishnamoorthy17@gmail.com",
                'projectManager' => $users->get(13)?->email_address,
                'teamManager' => $users->get(14)?->email_address,

                'adminEmail' => $users->get('Admin')?->email_address,
                // 'employee' => $userDetails->email_address

            ];

            Log::info('Email list:', ['emails' => $emails]);

            $employeedetails = User::with(['createdByUser'])->select('id', 'employee_name', 'employee_type', 'position', 'department', 'date_of_joining', 'phone_number', 'email_address', 'profile_image')->where('id', $createdby)->first();
            $projectDetails = EntryProcessModel::select('id', 'entry_date', 'title', 'project_id', 'type_of_work', 'email', 'institute', 'department', 'profession', 'budget', 'process_status', 'journal', 'journal_status_date', 'journal_assigned_date', 'journal_status', 'hierarchy_level', 'writer', 'writer_status', 'writer_assigned_date', 'writer_status_date', 'reviewer', 'reviewer_assigned_date', 'reviewer_status', 'reviewer_status_date', 'statistican', 'statistican_assigned_date', 'statistican_status', 'statistican_status_date', 'created_by', 'project_status', 'assign_by', 'assign_date', 'projectduration')->where('id', $id)->first();

            if (! empty($emails['projectManager']) && ! empty($emails['teamManager']) && ! empty($emails['adminEmail'])) {
                try {
                    // Send email to writer with CC to others
                    Mail::to($emails['projectManager'], $emails['teamManager'])
                        ->cc($emails['adminEmail'])
                        ->send(new CorrectionNotificationMail([
                            'projectManagerEmail' => $emails['projectManager'],
                            'teamManagerEmail' => $emails['teamManager'],
                            'adminEmail' => $emails['adminEmail'],
                            // 'employee' => $emails['employee'],
                            // 'writer_status' => $project->process_status,
                            'employee_name' => $employeedetails->employee_name,
                            'role' => $employeedetails->createdByUser?->name,
                            'phone_number' => $employeedetails->phone_number,
                            'project_id' => $projectDetails->project_id,
                            'type' => $type,
                            'status' => $status,
                            // 'detail_name' => $request->status
                        ]));

                    return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
                } catch (\Exception $e) {
                    Log::error('Mail failed: '.$e->getMessage());

                    return response()->json(['success' => false, 'message' => 'Failed to send email.']);
                }
            } else {
                Log::error('One or more email addresses are missing.');

                return response()->json(['success' => false, 'message' => 'One or more email addresses are missing.']);
            }

            // $correction_tc = ProjectAssignDetails::where('project_id', $id)
            //     ->where('type', 'team_coordinator')
            //     // ->where('status', 'correction')
            //     ->orderBy('id', 'desc')
            //     ->first();

            // if ($correction_tc) {
            //     $correction_tc->project_id = $id;
            //     $correction_tc->assign_user = $createdby;
            //     $correction_tc->status = $status;
            //     $correction_tc->created_by = $createdby;
            //     $correction_tc->updated_at = now();
            //     $correction_tc->save();
            // } else {

            //     ProjectAssignDetails::create([

            //         'project_id' => $id,
            //         'assign_user' => $createdby,
            //         'status' => $status,
            //         'type' => 'team_coordinator',
            //         // 'type_sme' => $type_sme,
            //         'created_by' => $createdby,
            //         'is_deleted' => $delete,
            //     ]);
            // }

            // $checkproject_log = ProjectLogs::where('project_id', $id)->where('employee_id', $assign_user)->where('status_type', $assign_user_type)->latest()->first();

            // //project logs
            // ProjectLogs::create([
            //     'project_id' => $id,
            //     'employee_id' => $assign_user,
            //     'assigned_date' => $assigned_date,
            //     'status' => $status,
            //     'status_date' => $status_date,
            //     'status_type' => $type,
            //     // 'assing_preview_id' => $checkproject_log->id,
            //     'assing_preview_id' => optional(ProjectLogs::where('project_id', $id)->where('employee_id', $assign_user)->where('status_type', $assign_user_type)->latest()->first())->id,
            //     'created_by' => $createdby,
            //     'created_date' => date('Y-m-d H:i:s'),
            // ]);
        } else {

            // if ($type === 'team_coordinator') {

            //     // If no correction exists -> create one
            //     $project_tc = ProjectAssignDetails::where('project_id', $project_number)
            //         ->where('type', 'team_coordinator')
            //         // ->where('status', 'correction')
            //         ->orderBy('id', 'desc')
            //         ->first();

            //     if ($project_tc) {
            //         // Update existing correction row
            //         $project_tc->type_sme   = $type_sme;
            //         $project_tc->status   = 'correction';
            //         $project_tc->created_by = $createdby;
            //         $project_tc->updated_at = now();
            //         $project_tc->save();

            //     $sme_tc = ProjectAssignDetails::where('project_id', $id)
            //         ->where('type', 'sme')
            //         // ->where('status', 'correction')
            //         ->orderBy('id', 'desc')
            //         ->first();

            //     if ($sme_tc) {
            //         $sme_tc->type_sme   = $type_sme;
            //         $sme_tc->status   = 'correction';
            //         $sme_tc->type = $type;
            //         $sme_tc->created_by = $createdby;
            //         $sme_tc->updated_at = now();
            //         $sme_tc->save();
            //     }
            //     } else {
            //         // Create new correction row
            //         $project_tc = ProjectAssignDetails::create([
            //             'project_id'  => $id,
            //             'assign_user' => $createdby,
            //             'status'      => $status,  // correction
            //             'type'        => $type,    // team_coordinator
            //             'type_sme'    => $type_sme,
            //             'created_by'  => $createdby,
            //             'is_deleted'  => $delete,
            //         ]);
            //     }

            //     // ✅ After creating/updating correction, check if type_sme is Publication Manager
            //     if ($type_sme === 'Publication Manager') {
            //         $journal_tc = ProjectAssignDetails::where('project_id', $id)
            //             ->where('type', 'publication_manager')
            //             // ->where('status', 'reviewer_comments')
            //             ->latest()
            //             ->first();

            //         if ($journal_tc) {
            //             $journal_tc->update([
            //                 'status'     => '-',
            //                 'created_by' => $createdby,
            //                 'updated_at' => now()
            //             ]);
            //         }
            //     }
            // }

            //new
            if ($type === 'team_coordinator') {
                // 1️⃣ Try to get team_coordinator
                $project_tc = ProjectAssignDetails::where('project_id', $id)
                    ->where('type', 'team_coordinator')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($project_tc) {
                    // Update team_coordinator
                    $project_tc->update([
                        'type_sme' => $type_sme,
                        'status' => 'correction',
                        'created_by' => $createdby,
                        'updated_at' => now(),
                    ]);

                    $projectEntry = EntryProcessModel::where('id', $id)
                        ->whereIn('process_status', ['client_review', 'pending_author'])
                        ->first();

                    if ($projectEntry) {
                        $projectEntry->process_status = 'in_progress';
                        $projectEntry->save();
                    }

                    $activity = new ProjectActivity;
                    $activity->project_id = $id;
                    // $activity->activity = $status.' assigned to '.$userDetails->employee_name;
                    $activity->activity = 'Correction assigned to TC';
                    $activity->role = 'sme';
                    $activity->created_by = $createdby;
                    $activity->created_date = now();
                    $activity->save();
                } else {
                    // 2️⃣ If not found, try to get sme
                    $sme_tc = ProjectAssignDetails::where('project_id', $id)
                        ->where('type', 'sme')
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($sme_tc) {
                        // Update sme (convert to team_coordinator)
                        $sme_tc->update([
                            'type_sme' => $type_sme,
                            'status' => 'correction',
                            'type' => 'team_coordinator',  // overwrite type
                            'created_by' => $createdby,
                            'updated_at' => now(),
                        ]);
                    } else {
                        // 3️⃣ If both not found → create new team_coordinator
                        ProjectAssignDetails::create([
                            'project_id' => $id,
                            'assign_user' => $createdby,
                            'status' => 'correction',
                            'type' => 'team_coordinator',
                            'type_sme' => $type_sme,
                            'created_by' => $createdby,
                            'is_deleted' => $delete,
                        ]);

                        $projectEntry = EntryProcessModel::where('id', $id)
                            ->whereIn('process_status', ['client_review', 'pending_author'])
                            ->first();

                        if ($projectEntry) {
                            $projectEntry->process_status = 'in_progress';
                            $projectEntry->save();
                        }

                        $activity = new ProjectActivity;
                        $activity->project_id = $id;
                        // $activity->activity = $status.' assigned to '.$userDetails->employee_name;
                        $activity->activity = 'Correction assigned to TC';
                        $activity->role = 'sme';
                        $activity->created_by = $createdby;
                        $activity->created_date = now();
                        $activity->save();
                    }
                }

                // ✅ Extra condition: if type_sme is Publication Manager
                if ($type_sme === 'Publication Manager') {
                    $journal_tc = ProjectAssignDetails::where('project_id', $id)
                        ->where('type', 'publication_manager')
                        ->latest()
                        ->first();

                    if ($journal_tc) {
                        $journal_tc->update([
                            'status' => '-',
                            'created_by' => $createdby,
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            if ($type === 'sme') {
                $tcCorrection = ProjectAssignDetails::where('project_id', $id)
                    // ->where('id', $emp_id)
                    ->where('type', 'team_coordinator')
                    ->where('status', 'correction')
                    ->orderBy('id', 'desc')->first();

                if ($tcCorrection) {
                    $tcCorrection->status = $status;
                    $tcCorrection->type = $type;
                    $tcCorrection->created_by = $createdby;
                    $tcCorrection->save();
                } else {
                    ProjectAssignDetails::create([
                        'project_id' => $id,
                        'assign_user' => $createdby,
                        'status' => $status,
                        'type' => $type,
                        'created_by' => $createdby,
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Status updated successfully']);
    }

    public function getProjectActivity(Request $request)
    {

        $type_of_work = $request->query('type_of_work') ?? 'all';

        $deletedList = ProjectActivity::with(['entryProcessModel', 'createdByUser'])
            ->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        if (isset($type_of_work) && $type_of_work != 'all') {
            $deletedList = $deletedList->where('project_id', $type_of_work);
        }

        $deletedList = $deletedList->get();

        // Get an array of unique project_ids from the deleted activities
        $projectIds = $deletedList->pluck('project_id')->unique()->toArray();

        // Fetch project titles for the related project IDs
        $projectTitle = EntryProcessModel::whereIn('id', $projectIds)
            ->orderBy('created_at', 'desc')
            ->select('id', 'project_id')
            ->get();

        return response()->json([
            'details' => $deletedList,
            'projectTitle' => $projectTitle,
        ]);
    }

    public function getProjectAssignDelete(Request $request, $id)
    {
        $projectassign = ProjectAssignDetails::find($id);

        if ($projectassign) {
            $projectassign->delete(); // Permanently delete the record

            return response()->json(['message' => 'Reviewer deleted successfully'], 200);
        } else {
            return response()->json(['error' => 'Reviewer not found'], 404);
        }
    }

    // public function updateProjectStatus(Request $request)
    // {
    //     $project_id = $request->project_id;
    //     $emp_id = $request->employee_id;
    //     $new_emp_id = $request->new_employee_id;
    //     $type  = $request->type;

    //      if ($type === 'revert') {
    //         $details = ProjectAssignDetails::where('project_id', $project_id)
    //             ->where('status', 'revert')
    //             ->first();

    //         if ($details) {
    //             $details->assign_user = $new_emp_id;
    //             $details->save();
    //         }
    //     }

    //     $projectLog = ProjectLogs::where('employee_id', $emp_id)->where('project_id', $project_id)->oldest()->first();

    //     $projectAssign = ProjectAssignDetails::with(['employee_rejected' => function ($query) use ($emp_id) {
    //         $query->where('status', 'rejected')->where('employee_id', $emp_id); // Filtering rejected employees based on employee_id
    //     }])->get();

    //     //projectStatus for updating the rejected into pending
    //     $projectStatus = ProjectStatus::where('project_id', $project_id)->where('assign_id', $emp_id)->first();
    //     if ($projectStatus) {
    //         $projectStatus->assign_id = $new_emp_id;
    //         $projectStatus->status = "pending";
    //         $projectStatus->save();
    //     } else {
    //         $projectstatus = new ProjectStatus();
    //         $projectstatus->project_id = $project_id;
    //         $projectstatus->assign_id = $new_emp_id;
    //         $projectstatus->status = "pending";
    //         $projectstatus->save();
    //     }

    //     $projectLogs = $projectAssign->where('assign_user', $emp_id)->where('project_id', $project_id);

    //     // Loop through each log and update as needed
    //     foreach ($projectLogs as $log) {
    //         if ($log->employee_rejected->isNotEmpty()) {
    //             // Update the main assign_user
    //             $log->assign_user = $new_emp_id;
    //             $log->status = $projectLog->status;
    //             $log->save();

    //             // Update each matching rejected employee record
    //             // foreach ($log->employee_rejected as $rejected) {
    //             //     $rejected->employee_id = $new_emp_id;
    //             //     $rejected->status = $log->status; // set to main status
    //             //     $rejected->save();
    //             // }
    //         }
    //     }

    //     return $projectLogs;

    //     // If type is 'revert', update the revert assignment as well

    // }

    public function updateProjectStatus(Request $request)
    {
        $project_id = $request->project_id;
        $emp_id = $request->employee_id;
        $new_emp_id = $request->new_employee_id;
        $type = $request->type;

        if ($type === 'revert') {
            $details = ProjectAssignDetails::where('project_id', $project_id)
                ->where('status', 'revert')
                ->where('assign_user', $emp_id)
                ->first();

            if ($details) {
                $details->assign_user = $new_emp_id;
                $details->status = 'to_do';
                $details->save();
            }

            $revertStatus = ProjectStatus::where('project_id', $project_id)
                ->where('assign_id', $emp_id)
                ->where('status', 'accepted')
                ->first();

            if ($revertStatus) {
                $revertStatus->assign_id = $new_emp_id;
                $revertStatus->status = 'accepted';
                $revertStatus->save();
            }
        }

        $projectLog = ProjectLogs::where('employee_id', $emp_id)
            ->where('project_id', $project_id)
            ->oldest()
            ->first();

        $projectAssign = ProjectAssignDetails::with(['employee_rejected' => function ($query) use ($emp_id) {
            $query->where('status', 'rejected')->where('employee_id', $emp_id);
        }])
            ->where('assign_user', $emp_id)
            ->where('project_id', $project_id)
            ->get();

        $statusToApply = $projectLog ? $projectLog->status : 'pending';

        // Update or create the project status
        $existingStatus = ProjectStatus::where('project_id', $project_id)
            ->where('assign_id', $emp_id)
            ->first();

        if ($existingStatus) {
            $existingStatus->assign_id = $new_emp_id;
            $existingStatus->status = 'pending';
            $existingStatus->save();
        } else {

            ProjectStatus::create([
                'project_id' => $project_id,
                'assign_id' => $new_emp_id,
                'status' => 'pending',
            ]);
        }

        // Update project assignment records
        foreach ($projectAssign as $log) {
            if ($log->employee_rejected->isNotEmpty()) {
                $log->assign_user = $new_emp_id;
                $log->status = 'to_do';
                // $log->status = $statusToApply;
                $log->save();

                // Optional: Update rejected employees
                /*
            foreach ($log->employee_rejected as $rejected) {
                $rejected->employee_id = $new_emp_id;
                $rejected->status = $statusToApply;
                $rejected->save();
            }
            */
            }
        }

        return $projectAssign;
    }

    public function getFreelancerDetails(Request $request)
    {
        $position = $request->query('position');
        $assignUserId = $request->query('assign_user');

        $freelancer = People::where('status', 1)
            ->where('id', $assignUserId)
            ->first();

        if (! $freelancer) {
            return response()->json(['message' => 'Freelancer not found'], 404);
        }

        $details = EntryProcessModel::whereHas('statusDatas', function ($query) use ($assignUserId) {
            $query->where('assign_user', $assignUserId);
        })
            // ->whereHas('employeePaymentDetails', function ($query) use ($position) {
            //     $query->where('type', $position);
            // })
            ->with([
                'employeePaymentDetails' => function ($query) use ($assignUserId) {
                    $query->where('employee_id', $assignUserId);
                },
                // 'employeePaymentDetails' => function ($query) {
                //     $query->where('type', 'writer')->where('employee_id' , $assignUserId);
                // },
                'statusDatas' => function ($query) use ($assignUserId) {
                    $query->where('assign_user', $assignUserId);
                },
            ])
            ->select('id', 'project_id', 'type_of_work', 'title', 'process_status', 'entry_date', 'projectduration')
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('project_id');

        return response()->json([
            'details' => $details,
        ]);
    }

    public function getEmployeeNames()
    {
        try {
            // Query the database to fetch all employees
            $employees = DB::connection('mysql_medics_hrms')
                ->table('employee_details')
                ->get();

            $result = [];

            foreach ($employees as $employee) {
                $result[] = [
                    'id' => $employee->id,
                    'name' => $employee->employee_name,
                ];
            }

            // Return result as JSON response
            return response()->json($result);
        } catch (\Exception $e) {
            // Log the error and return a response if the query fails
            Log::error($e->getMessage());

            return response()->json(['error' => 'Unable to fetch data'], 500);
        }
    }

    public function getEmployeeNameReport()
    {
        try {
            $employees = DB::connection('mysql_medics_hrms')
                ->table('employee_details')
                ->whereNotIn('position', ['Admin', '13', '14', '27', '28', '23', '42', '11'])
                ->select('id', 'employee_name', 'position')
                ->get();

            $result = [];

            foreach ($employees as $employee) {
                // If you need to get creator name from users table
                // Load the creator relationship (you'll need to use Eloquent model instead of DB query)
                // Or query users table directly
                if ($employee->position === '7,8') {
                    $creator = 'Writer, Reviewer';
                } else {
                    $creator = DB::connection('mysql_medics_hrms')
                        ->table('roles')
                        ->where('id', $employee->position)
                        ->value('name') ?? 'Admin';
                }

                $result[] = [
                    'id' => $employee->id,
                    'name' => $employee->employee_name,
                    'position' => $employee->position,
                    'role' => $creator,
                ];
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json(['error' => 'Unable to fetch data'], 500);
        }
    }

    public function projectLog()
    {
        $log = ProjectLogs::with(['userData', 'entryProcess'])
            ->orderBy('created_date', 'desc')
            ->get();

        return response()->json(['log' => $log]);
    }
}
