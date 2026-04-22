<?php
namespace App\Console\Commands;

use App\Repositories\ProjectRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportSheetProjects extends Command
{
    protected $signature = 'projects:import-sheet {--dry-run : Preview without writing}';
    protected $description = 'Bulk-import projects from the tracking sheet (one-time).';

    public function handle(ProjectRepository $projects): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $emailToId = DB::table('team_members')
            ->whereNull('deleted_at')
            ->pluck('id', 'email')
            ->toArray();

        $resolve = function (?string $email) use ($emailToId) {
            return $email ? ($emailToId[$email] ?? null) : null;
        };

        $creatorId = $emailToId['admin@myewards.com'] ?? null;
        if (!$creatorId) {
            $this->error('Creator user admin@myewards.com not found. Aborting.');
            return self::FAILURE;
        }

        $rows = $this->projectDefinitions();

        $this->info(sprintf('%d projects to import.', count($rows)));
        if ($dryRun) {
            $this->warn('DRY RUN — no database writes.');
        }

        $created = 0;
        $skipped = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $ownerId = $resolve($row['owner_email']) ?? $creatorId;
                if (!$ownerId) {
                    $skipped[] = "#{$i} {$row['name']} — owner not resolvable";
                    continue;
                }

                $data = [
                    'name'               => $row['name'],
                    'description'        => $row['description'] ?? null,
                    'objective'          => $row['objective'] ?? null,
                    'status'             => $row['status'] ?? 'active',
                    'priority'           => $row['priority'] ?? 'medium',
                    'owner_id'           => $ownerId,
                    'created_by'         => $creatorId,
                    'work_type'          => $row['work_type'] ?? null,
                    'task_type'          => $row['task_type'] ?? null,
                    'ticket_link'        => $row['ticket_link'] ?? null,
                    'analyst_id'         => $resolve($row['analyst_email'] ?? null),
                    'analyst_testing_id' => $resolve($row['analyst_testing_email'] ?? null),
                    'developer_id'       => $resolve($row['developer_email'] ?? null),
                    'document_link'      => $row['document_link'] ?? null,
                    'ai_chat_link'       => $row['ai_chat_link'] ?? null,
                ];

                if ($dryRun) {
                    $this->line(sprintf('  [dry] #%d %s — stage=%s owner=%d dev=%s',
                        $i + 1, $row['name'], $row['stage'], $ownerId,
                        $data['developer_id'] ?? 'null'
                    ));
                    $created++;
                    continue;
                }

                $projectId = $projects->create($data);

                DB::table('project_stages')->insert([
                    'project_id' => $projectId,
                    'stage_name' => $row['stage'],
                    'updated_by' => $creatorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('project_workers')->insert([
                    'project_id'  => $projectId,
                    'user_id'     => $ownerId,
                    'role'        => 'owner',
                    'assigned_by' => $creatorId,
                    'assigned_at' => now(),
                ]);

                $created++;
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info("Dry run: would create {$created} projects.");
            } else {
                DB::commit();
                $this->info("Imported {$created} projects.");
            }

            if ($skipped) {
                $this->warn('Skipped:');
                foreach ($skipped as $s) {
                    $this->line('  - ' . $s);
                }
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed, rolled back: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function projectDefinitions(): array
    {
        return [
            [
                'name' => 'Schema Mapping - Database Table Fields Description Mapping',
                'description' => 'Database - Table Fields Description Mapping',
                'work_type' => 'backend_work', 'task_type' => 'data_mapping',
                'stage' => 'doc_wip',
                'owner_email' => 'rohit@myewards.com', 'analyst_email' => 'rohit@myewards.com',
                'document_link' => 'https://docs.google.com/spreadsheets/d/16eNsmecAgMBeZivO7j8jVStwYjMvk13-INyeQBCQuC8/edit?usp=sharing',
            ],
            [
                'name' => 'LifeCycle Campaigns Dashboard',
                'description' => 'Create a new dashboard where one can see all their IG/AE campaigns - segment wise',
                'work_type' => 'full_stack', 'task_type' => 'new_project',
                'stage' => 'need_to_discuss',
                'owner_email' => 'ayashree@arjava.in', 'analyst_email' => 'ayashree@arjava.in',
                'analyst_testing_email' => 'ayashree@arjava.in', 'developer_email' => 'soumyadip@myewards.com',
                'ai_chat_link' => 'https://claude.ai/share/42e33974-7d29-408b-87af-044a9e31e32e',
            ],
            [
                'name' => 'Old Functions Optimization',
                'description' => 'Existing Live Functions optimization',
                'work_type' => 'backend_work', 'task_type' => 'addition_on_existing',
                'stage' => 'gpt_chat_wip',
                'owner_email' => 'ayashree@arjava.in', 'analyst_email' => 'ayashree@arjava.in',
                'document_link' => 'https://docs.google.com/document/d/1iZGQNekMrOWOojKDeqzb05ZYQHeC4hTRLTJIYj9pU9E/edit?tab=t.0',
            ],
            [
                'name' => 'New Admin Dashboard - Audience', 'description' => 'Audience module',
                'work_type' => 'full_stack', 'task_type' => 'addition_on_existing',
                'stage' => 'doc_yet_to_start',
                'owner_email' => 'poulomi.mondal@myewards.in',
            ],
            [
                'name' => 'New Admin Dashboard - Campaigns', 'description' => 'Campaigns module',
                'work_type' => 'full_stack', 'task_type' => 'addition_on_existing',
                'stage' => 'doc_yet_to_start',
                'owner_email' => 'poulomi.mondal@myewards.in',
            ],
            [
                'name' => 'New Admin Dashboard - Bulk Promo', 'description' => 'Bulk Promo',
                'work_type' => 'full_stack', 'task_type' => 'addition_on_existing',
                'stage' => 'vibe_code_shared',
                'owner_email' => 'poulami@myewards.com', 'analyst_email' => 'poulami@myewards.com',
                'document_link' => 'Bulk promo figma link',
            ],
            [
                'name' => 'Outlet Modification',
                'work_type' => 'full_stack', 'task_type' => 'addition_on_existing',
                'stage' => 'vibe_code_shared',
                'owner_email' => 'poulami@myewards.com', 'analyst_email' => 'poulami@myewards.com',
            ],
            [
                'name' => 'Membership Category',
                'work_type' => 'full_stack', 'task_type' => 'addition_on_existing',
                'stage' => 'vibe_code_shared',
                'owner_email' => 'poulami@myewards.com', 'analyst_email' => 'poulami@myewards.com',
            ],
            [
                'name' => 'Membership - Advance Loyalty',
                'work_type' => 'full_stack', 'task_type' => 'addition_on_existing',
                'stage' => 'testing_yet_to_start',
                'owner_email' => 'poulami@myewards.com', 'analyst_email' => 'poulami@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'soumyadip@myewards.com',
            ],
            [
                'name' => 'L1 Check V2 - Client Facing',
                'description' => 'V2 - Client Facing - Replication of l1 check app but with merchant login screen and little flow changes',
                'objective' => 'List of additions added in the ticket. Work will be added here one by one for doc and dev.',
                'priority' => 'high',
                'work_type' => 'full_stack', 'task_type' => 'addition_on_existing',
                'ticket_link' => '9179', 'stage' => 'development_wip',
                'owner_email' => 'rohit@myewards.com', 'analyst_email' => 'rohit@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'sagnik@myewards.in',
                'document_link' => 'https://docs.google.com/document/d/19duuW7TNhe4XgMsB7z8_bTxZRSYKkjpNiL0D96RbQ5U/edit?tab=t.0',
                'ai_chat_link' => 'https://claude.ai/share/014194e0-038c-419f-baf8-21d086174e96',
            ],
            [
                'name' => 'ROCC Email Delivery Status Mapping',
                'description' => 'ROCC for Email Delivery Status mapping (Email Rates in ROCC)',
                'objective' => 'Will start after testing of Delivery Status Standardization and WhatsApp Standard Delivery Mapping.',
                'work_type' => 'backend_work', 'task_type' => 'data_mapping',
                'ticket_link' => '8078', 'stage' => 'dev_yet_to_start',
                'owner_email' => 'poulomi.mondal@myewards.in', 'analyst_email' => 'poulomi.mondal@myewards.in',
                'analyst_testing_email' => 'poulomi.mondal@myewards.in',
                'document_link' => 'EMAIL STANDARD STATUS MAPPING',
                'ai_chat_link' => 'https://chatgpt.com/share/698abcd0-3f70-800d-9bc6-8aa72b460504',
            ],
            [
                'name' => 'SMS Delivery Mapping', 'description' => 'SMS delivery mapping',
                'objective' => 'Will start after testing of Delivery Status Standardization and WhatsApp Standard Delivery Mapping.',
                'work_type' => 'backend_work', 'task_type' => 'data_mapping',
                'ticket_link' => '8079', 'stage' => 'dev_yet_to_start',
                'owner_email' => 'poulomi.mondal@myewards.in', 'analyst_email' => 'poulomi.mondal@myewards.in',
                'analyst_testing_email' => 'rohit@myewards.com',
                'document_link' => 'SMS STANDARD STATUS MAPPING',
                'ai_chat_link' => 'https://chatgpt.com/share/6989a648-0188-800d-844a-34b0caed244d',
            ],
            [
                'name' => 'WhatsApp Standard Status Mapping', 'description' => 'WhatsApp Standard Status Mapping',
                'work_type' => 'backend_work', 'task_type' => 'data_mapping',
                'ticket_link' => '8080', 'stage' => 'dev_yet_to_start',
                'owner_email' => 'poulomi.mondal@myewards.in', 'analyst_email' => 'poulomi.mondal@myewards.in',
                'analyst_testing_email' => 'somdeb@myewards.in',
                'document_link' => 'WHATSAPP STANDARD STATUS MAPPING',
                'ai_chat_link' => 'https://claude.ai/share/ad6b51f7-3ae5-44f7-a6c7-2772b4d5ad9c',
            ],
            [
                'name' => 'Report Download History',
                'description' => 'Report Download History for Report Dashboard',
                'objective' => 'Reports covered: Reward Verified, Tax-Wise Billing, Wallet Issued, Wallet Redeemed, Breach, Close-ended Promo Issuance/Redeemed, WL App Profile Deletion, OTP Auth Bypass, Wallet Expired.',
                'work_type' => 'backend_work', 'task_type' => 'addition_on_existing',
                'stage' => 'development_wip',
                'owner_email' => 'somdeb@myewards.in', 'analyst_email' => 'somdeb@myewards.in',
                'analyst_testing_email' => 'somdeb@myewards.in', 'developer_email' => 'subhra@myewards.in',
            ],
            [
                'name' => 'Report Download History (Column-wise Filter)',
                'description' => 'Report Download History with column filter',
                'objective' => 'Reports covered: Wallet Issued/Redeemed, Breach, OTP Auth Bypass, WL App Profile Deletion, Close-ended Promo Redeemed/Issuance, Wallet Expired, Credits Given, Item-Wise Billing, Invalid Bill, Promos Redeemed, Upcoming Credit Expiry, Expired Credit, Check In, Coupon Booklet Issue, eWallets Issued.',
                'work_type' => 'backend_work', 'task_type' => 'addition_on_existing',
                'stage' => 'development_wip',
                'owner_email' => 'somdeb@myewards.in', 'analyst_email' => 'somdeb@myewards.in',
                'analyst_testing_email' => 'somdeb@myewards.in', 'developer_email' => 'subhra@myewards.in',
            ],
            [
                'name' => 'Performance Report Data - Modifications based on RFM Segments',
                'work_type' => 'backend_work', 'task_type' => 'addition_on_existing',
                'stage' => 'dev_yet_to_start',
                'owner_email' => 'ayashree@arjava.in', 'analyst_email' => 'ayashree@arjava.in',
                'developer_email' => 'subhra@myewards.in',
            ],
            [
                'name' => 'Event Promo', 'description' => 'Event Promo',
                'work_type' => 'full_stack', 'task_type' => 'addition_on_existing',
                'stage' => 'dev_changes_required',
                'owner_email' => 'admin@myewards.com', 'developer_email' => 'sneha@myewards.com',
            ],
            [
                'name' => 'Monthly Campaign Responder Track with RFM Segregation & Report',
                'description' => 'Create 3 functions to map data in a new table month-year basis: (1) Responder list + counts (txns, revenue, weekday details). (2) RFM distribution (Active, At-Risk, Lost, Dormant). (3) Active segment distribution (Engaged, Disengaged, Drop-Off).',
                'objective' => 'Done in ChatGPT; doc sent to Subhankar for review.',
                'work_type' => 'backend_work', 'task_type' => 'data_mapping',
                'ticket_link' => '8873', 'stage' => 'dev_yet_to_start',
                'owner_email' => 'ayashree@arjava.in', 'analyst_email' => 'ayashree@arjava.in',
                'analyst_testing_email' => 'ayashree@arjava.in', 'developer_email' => 'sneha@myewards.com',
                'document_link' => 'Campaign Responder For InsightX',
                'ai_chat_link' => 'https://chatgpt.com/share/6936a5c8-7ad4-800d-ab25-0e69c5f1ecfd',
            ],
            [
                // Developer "Sariful" not in team — developer_email intentionally omitted.
                'name' => 'InsightX - Generate Report based on RFM Status',
                'description' => 'Generate report based on RFM status',
                'work_type' => 'backend_work', 'task_type' => 'new_project',
                'ticket_link' => '8481', 'stage' => 'development_wip',
                'owner_email' => 'admin@myewards.com',
            ],
            [
                'name' => 'Business Internal App - SMS Balance API Check (Frontend)',
                'description' => 'SMS Balance API Check - L1 Checks and Other Functionalities',
                'objective' => 'Published in Playstore.',
                'status' => 'on_hold',
                'work_type' => 'frontend_work', 'task_type' => 'addition_on_existing',
                'ticket_link' => '8845', 'stage' => 'on_hold',
                'owner_email' => 'rohit@myewards.com', 'analyst_email' => 'rohit@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'sagnik@myewards.in',
            ],
            [
                'name' => 'Business Internal App - SMS Balance API Check (Backend)',
                'description' => 'SMS Balance API Check - L1 Checks and Other Functionalities',
                'objective' => 'Shifted for development.',
                'status' => 'on_hold',
                'work_type' => 'backend_work', 'task_type' => 'addition_on_existing',
                'ticket_link' => '8845', 'stage' => 'on_hold',
                'owner_email' => 'rohit@myewards.com', 'analyst_email' => 'rohit@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'sneha@myewards.com',
                'document_link' => 'https://docs.google.com/document/d/1LR3i280Df0Je89n1akrSoiLISY7eKU0at6RNp6LcS_g/edit?tab=t.0',
            ],
            [
                'name' => 'POS - Ginesys Return Bill Calculation and Mapping',
                'description' => 'Ginesys return bill calculation and new mapping logic for multiple bill items sent at the same time',
                'objective' => 'Explained by Zahrul.',
                'work_type' => 'backend_work', 'task_type' => 'data_mapping',
                'ticket_link' => '4131', 'stage' => 'dev_yet_to_start',
                'owner_email' => 'rohit@myewards.com', 'analyst_email' => 'rohit@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'subhankar@myewards.com',
                'ai_chat_link' => 'https://chatgpt.com/share/696089c7-f350-800d-9e20-0e6bc27f5127',
            ],
            [
                'name' => 'Fraud Module Phase 1.2 - Communication (Immediate Trigger)',
                'description' => 'Fraud Module Phase 1.2 Communication Part - Immediate trigger',
                'objective' => 'Subhankar will develop the trigger part.',
                'priority' => 'high',
                'work_type' => 'backend_work', 'task_type' => 'addition_on_existing',
                'ticket_link' => '9171', 'stage' => 'dev_yet_to_start',
                'owner_email' => 'rohit@myewards.com', 'analyst_email' => 'rohit@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'subhankar@myewards.com',
                'document_link' => 'https://docs.google.com/document/d/1cGSU4ezsnCM3UZFFzM1yrdOPo3lp1tWdfJG4zPVISL4/edit?tab=t.0',
                'ai_chat_link' => 'https://claude.ai/share/ae4145c3-3154-48c5-bebd-5c2e7cc2fd9e',
            ],
            [
                'name' => 'eWARDS Learning Hub - Course Module', 'description' => 'Course Module',
                'work_type' => 'full_stack', 'task_type' => 'new_project',
                'stage' => 'development_wip',
                'owner_email' => 'somdeb@myewards.in', 'analyst_email' => 'somdeb@myewards.in',
            ],
            [
                'name' => 'New Admin Dashboard - Fraud Module Setup Alerts',
                'description' => 'Fraud module setup alerts',
                'work_type' => 'full_stack', 'task_type' => 'addition_on_existing',
                'stage' => 'development_wip',
                'owner_email' => 'rohit@myewards.com', 'analyst_email' => 'rohit@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'soumyadip@myewards.com',
            ],
            [
                'name' => 'Setup Section / Order Types / Tables (Frontend)',
                'description' => 'Setup Section / Order Types / Tables',
                'objective' => 'New Admin Dashboard - Testing WIP.',
                'work_type' => 'frontend_work', 'task_type' => 'addition_on_existing',
                'stage' => 'testing_wip',
                'owner_email' => 'poulomi.mondal@myewards.in', 'analyst_email' => 'poulomi.mondal@myewards.in',
                'analyst_testing_email' => 'poulomi.mondal@myewards.in', 'developer_email' => 'soumyadip@myewards.com',
            ],
            [
                'name' => 'Setup Section / Order Types / Tables (Backend)',
                'description' => 'Setup Section / Order Types / Tables',
                'work_type' => 'backend_work', 'task_type' => 'addition_on_existing',
                'stage' => 'development_wip',
                'owner_email' => 'poulomi.mondal@myewards.in', 'analyst_email' => 'poulomi.mondal@myewards.in',
                'analyst_testing_email' => 'poulomi.mondal@myewards.in', 'developer_email' => 'subhra@myewards.in',
            ],
            [
                'name' => 'Cron & Report Dashboard - AE Mapping & Campaign Attribution Reports',
                'description' => 'AE Mapping & Campaign Attribution Reports',
                'work_type' => 'full_stack', 'task_type' => 'new_project',
                'stage' => 'testing_wip',
                'owner_email' => 'sneha@myewards.com', 'analyst_email' => 'sneha@myewards.com',
                'analyst_testing_email' => 'poulomi.mondal@myewards.in', 'developer_email' => 'sneha@myewards.com',
            ],
            [
                'name' => 'New Admin Dashboard - Fraud Module: Setup Alerts (Testing)',
                'description' => 'Fraud Module: Setup Alerts',
                'objective' => 'List of additions added in the ticket. Work will be added here one by one for doc and dev.',
                'work_type' => 'full_stack', 'task_type' => 'addition_on_existing',
                'stage' => 'testing_wip',
                'owner_email' => 'rohit@myewards.com', 'analyst_email' => 'rohit@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'soumyadip@myewards.com',
                'document_link' => 'https://docs.google.com/document/d/19duuW7TNhe4XgMsB7z8_bTxZRSYKkjpNiL0D96RbQ5U/edit?tab=t.0',
                'ai_chat_link' => 'https://claude.ai/share/014194e0-038c-419f-baf8-21d086174e96',
            ],
            [
                // Developer "Sahaalam" not in team — developer_email intentionally omitted.
                'name' => 'Fraud Module Phase 1.2 - Communication Structure (Frontend)',
                'description' => 'Fraud Module Phase 1.2 Communication Part - Structure Part',
                'objective' => 'Goes live after report transaction trigger part is live.',
                'priority' => 'high',
                'work_type' => 'frontend_work', 'task_type' => 'addition_on_existing',
                'ticket_link' => '9171', 'stage' => 'ready_for_internal',
                'owner_email' => 'rohit@myewards.com', 'analyst_email' => 'rohit@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com',
                'document_link' => 'https://docs.google.com/document/d/1cGSU4ezsnCM3UZFFzM1yrdOPo3lp1tWdfJG4zPVISL4/edit?tab=t.0',
                'ai_chat_link' => 'https://claude.ai/share/ae4145c3-3154-48c5-bebd-5c2e7cc2fd9e',
            ],
            [
                'name' => 'Fraud Module Phase 1.2 - Communication Structure (Backend)',
                'description' => 'Fraud Module Phase 1.2 Communication Part - Structure Part',
                'objective' => 'Goes live after report transaction trigger part is live.',
                'priority' => 'high',
                'work_type' => 'backend_work', 'task_type' => 'addition_on_existing',
                'ticket_link' => '9171', 'stage' => 'ready_for_internal',
                'owner_email' => 'rohit@myewards.com', 'analyst_email' => 'rohit@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'sneha@myewards.com',
                'document_link' => 'https://docs.google.com/document/d/1cGSU4ezsnCM3UZFFzM1yrdOPo3lp1tWdfJG4zPVISL4/edit?tab=t.0',
                'ai_chat_link' => 'https://claude.ai/share/ae4145c3-3154-48c5-bebd-5c2e7cc2fd9e',
            ],
            [
                'name' => 'New Admin Dashboard - Booklets', 'description' => 'Booklets',
                'objective' => 'Testing WIP.',
                'work_type' => 'full_stack', 'task_type' => 'addition_on_existing',
                'stage' => 'testing_wip',
                'owner_email' => 'rohit@myewards.com', 'analyst_email' => 'rohit@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'subhra@myewards.in',
                'document_link' => 'https://drive.google.com/file/d/1T9ymBQ1blFh_TVWuEBLwEsm659hTqkr9/view?usp=drive_link',
            ],
            [
                'name' => 'WhatsApp Delivery Status Work', 'description' => 'WhatsApp delivery status work',
                'objective' => 'Mail sent. Goes live only after Delivery Status Standardizations task.',
                'work_type' => 'backend_work', 'task_type' => 'data_mapping',
                'stage' => 'ready_to_go_for_live',
                'owner_email' => 'poulomi.mondal@myewards.in', 'analyst_email' => 'poulomi.mondal@myewards.in',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'sneha@myewards.com',
                'document_link' => 'WhatsApp delivery status mapping',
            ],
            [
                'name' => 'Campaign Responder Count (MUL table mapping)',
                'description' => 'MUL table campaign count values mapping',
                'objective' => 'Historical data count by Subhankar first; then daily responder count function.',
                'work_type' => 'backend_work', 'task_type' => 'data_mapping',
                'ticket_link' => '8010', 'stage' => 'ready_to_go_for_live',
                'owner_email' => 'rohit@myewards.com',
                'analyst_testing_email' => 'rohit@myewards.com', 'developer_email' => 'sneha@myewards.com',
                'document_link' => 'Campaign Responder Doc',
            ],
        ];
    }
}
