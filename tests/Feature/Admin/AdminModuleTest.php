<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Qualification;
use App\Models\Program;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminModuleTest extends TestCase
{
    use RefreshDatabase; // Resets DB for every test

    protected $adminUser;
    protected $headers;

    /**
     * Setup: Create an Admin user and log them in before every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Admin Profile & User
        $adminProfile = Admin::create([
            'name' => 'Super Admin',
            'user_id' => 4 // Placeholder
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'profileable_id' => $adminProfile->id,
            'profileable_type' => Admin::class,
        ]);

        // 2. Act as this user (Sanctum Auth)
        $this->actingAs($this->adminUser);
    }

    /** @test */
    public function admin_can_create_academic_structure()
    {
        // 1. Create Department
        $deptResponse = $this->postJson('/api/admin/departments', [
            'name' => 'Mathematics',
            'code' => 'MAT'
        ]);
        $deptResponse->assertStatus(201);
        $this->assertDatabaseHas('departments', ['code' => 'MAT']);

        // 2. Create Qualification (Helper for next step)
        $qual = Qualification::create([
            'name' => 'Bachelor of Science',
            'code' => 'BSC'
        ]);

        // 3. Create Program (Requires Public IDs of Dept & Qual)
        $deptPublicId = $deptResponse->json('public_id'); // Get UUID from response

        $progResponse = $this->postJson('/api/admin/programs', [
            'name' => 'B.Sc Mathematics',
            'code' => 'MAT101',
            'total_semesters' => 8,
            'department_public_id' => $deptPublicId, // Using UUID
            'qualification_public_id' => $qual->public_id, // Using UUID
        ]);

        $progResponse->assertStatus(201);
        $this->assertDatabaseHas('programs', ['code' => 'MAT101']);
    }

    /** @test */
    public function admin_can_register_lecturer_and_id_is_auto_generated()
    {
        // Setup: We need a Department first
        $dept = Department::create(['name' => 'Computer Science', 'code' => 'CS']);

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'dr.doe@college.edu',
            'department_public_id' => $dept->public_id,
            'title' => 'Dr',
            'gender' => 'M',
            'qualification' => 'PhD',
            'national_id' => 'NAT123456',
            'employment_date' => '2025-01-15',
        ];

        $response = $this->postJson('/api/admin/lecturers', $payload);

        $response->assertStatus(201);

        // Assert: Check the Response Structure
        $response->assertJsonStructure([
            'message',
            'lecturer_id', // Important!
            'temp_password'
        ]);

        // Assert: Logic Check (CS + 25 + 01)
        // Expected ID: "CS-2501" (assuming this is the first lecturer)
        $expectedId = 'CS-' . date('y', strtotime('2025-01-15')) . '01';
        $this->assertEquals($expectedId, $response->json('lecturer_id'));

        // Assert: User account was created
        $this->assertDatabaseHas('users', ['email' => 'dr.doe@college.edu']);
    }

    /** @test */
    public function admin_can_register_student_and_id_is_auto_generated()
    {
        // Setup: Dept, Qual, Program
        $dept = Department::create(['name' => 'Arts', 'code' => 'ART']);
        $qual = Qualification::create(['name' => 'Diploma', 'code' => 'DPM']);

        $program = Program::create([
            'name' => 'Diploma in Design',
            'code' => 'ART200',
            'department_id' => $dept->id,
            'qualification_id' => $qual->id,
            'total_semesters' => 4
        ]);

        $payload = [
            'first_name' => 'Jane',
            'last_name' => 'Student',
            'email' => 'jane@student.edu',
            'program_public_id' => $program->public_id,
            'gender' => 'F',
            'national_id' => 'STU987654',
            'enrollment_date' => now()->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/admin/students', $payload);

        $response->assertStatus(201);

        // Assert: Check Logic (25 + DPM + 001)
        // Assuming current year is 2025
        $currentYear = date('y');
        $expectedId = $currentYear . 'DPM' . '001';

        // We verify the Database directly
        $this->assertDatabaseHas('students', [
            'email' => 'jane@student.edu',
            'student_id' => $expectedId
        ]);
    }

    /** @test */
    public function it_prevents_duplicate_email_registration()
    {
        // 1. Create a user
        User::factory()->create(['email' => 'taken@test.com']);

        // 2. Try to register a lecturer with same email
        $dept = Department::create(['name' => 'Test', 'code' => 'TST']);

        $response = $this->postJson('/api/admin/lecturers', [
            'first_name' => 'Duplicate',
            'last_name' => 'User',
            'email' => 'taken@test.com', // DUPLICATE
            'department_public_id' => $dept->public_id,
            'title' => 'Mr',
            'gender' => 'M',
            'qualification' => 'M.Sc',
            'national_id' => 'UNIQUE123',
            'employment_date' => '2025-01-01'
        ]);

        // 3. Expect 422 Unprocessable Entity
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }
}
