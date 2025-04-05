<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Models\Section;
use App\Models\Subject;

class RepairSectionSubjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lamms:repair-section-subjects {--force : Force repair even if table exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repair the section_subject relationship table and populate with default data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting section_subject table repair...');

        // Check if the section_subject table exists
        if (!Schema::hasTable('section_subject')) {
            $this->warn('section_subject table does not exist, creating table...');

            try {
                // Create the table
                Schema::create('section_subject', function ($table) {
                    $table->id();
                    $table->foreignId('section_id')->constrained()->onDelete('cascade');
                    $table->foreignId('subject_id')->constrained()->onDelete('cascade');
                    $table->timestamps();
                    $table->unique(['section_id', 'subject_id']);
                });

                $this->info('section_subject table created successfully');
            } catch (\Exception $e) {
                $this->error('Failed to create section_subject table: ' . $e->getMessage());
                Log::error('Failed to create section_subject table: ' . $e->getMessage());
                return 1;
            }
        } else {
            $this->info('section_subject table already exists');

            // If --force flag is not provided and table has data, exit
            if (!$this->option('force') && DB::table('section_subject')->count() > 0) {
                $this->info('Table already has data. Use --force to rebuild relationships.');
                return 0;
            }
        }

        // Get all sections and subjects
        $sections = Section::all();
        $subjects = Subject::all();

        $this->info("Found {$sections->count()} sections and {$subjects->count()} subjects");

        if ($sections->isEmpty() || $subjects->isEmpty()) {
            $this->warn('No sections or subjects found, cannot create relationships');
            return 1;
        }

        // For each section, create relationships with subjects
        $bar = $this->output->createProgressBar($sections->count());
        $bar->start();

        foreach ($sections as $section) {
            // Get default subjects (3-5 subjects per section)
            $subjectCount = min($subjects->count(), rand(3, 5));
            $sectionSubjects = $subjects->random($subjectCount);

            foreach ($sectionSubjects as $subject) {
                // Check if this assignment already exists
                $exists = DB::table('section_subject')
                    ->where('section_id', $section->id)
                    ->where('subject_id', $subject->id)
                    ->exists();

                if (!$exists) {
                    // Create the relationship
                    DB::table('section_subject')->insert([
                        'section_id' => $section->id,
                        'subject_id' => $subject->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Section-subject relationships repaired successfully');
        $this->info('Total section-subject relationships: ' . DB::table('section_subject')->count());

        return 0;
    }
}
