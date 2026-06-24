<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Student extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    public const SYSTEM_ROLES = [
        'student'    => 'Studente',
        'instructor' => 'Formatore',
        'admin'      => 'Amministratore',
        'professor'  => 'Docente (Schola)',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'company', 'job_title', 'role',
        'avatar_url', 'is_active', 'is_demo', 'must_change_password',
        'microsoft_id', 'auto_enroll_all_courses', 'birth_date',
        'library_rights_ack_at', 'school_id', 'username', 'is_secretary',
        'is_instructor',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'must_change_password' => 'boolean',
        'auto_enroll_all_courses' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'birth_date' => 'date',
        'library_rights_ack_at' => 'datetime',
        'is_secretary' => 'boolean',
        'is_instructor' => 'boolean',
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'student_course')
            ->withPivot('enrolled_at', 'expires_at', 'completed_at', 'is_active', 'notes', 'instructor_id')
            ->withTimestamps();
    }

    public function taughtCourses()
    {
        return $this->belongsToMany(Course::class, 'course_instructor', 'instructor_id', 'course_id')
            ->withTimestamps();
    }

    public function mentoredStudents()
    {
        return $this->hasMany(StudentCourse::class, 'instructor_id');
    }

    // Formatore corsi Officina = CAPACITÀ (flag is_instructor), come la segreteria
    // (is_secretary): così un account può essere docente Schola (role='professor')
    // E formatore insieme. Resta vero anche per il role legacy 'instructor'.
    public function isInstructor(): bool
    {
        return $this->role === 'instructor' || (bool) $this->is_instructor;
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Docente Schola — distinto da instructor (formatore corsi Officina).
    public function isProfessor(): bool
    {
        return $this->role === 'professor';
    }

    // Segreteria = CAPACITÀ (flag is_secretary), non un valore di role: così
    // un account può essere professore E segreteria insieme. Storico:
    // isSchoolAdmin() resta come alias del flag (call site invariati).
    public function isSecretary(): bool
    {
        return (bool) $this->is_secretary;
    }

    public function isSchoolAdmin(): bool
    {
        return $this->isSecretary();
    }

    // Corsista = CAPACITÀ-da-dato (iscrizioni corsi), ortogonale a role.
    public function hasCourseAccess(): bool
    {
        return $this->courses()->exists();
    }

    // ===== Fase 2: appartenenza scuola =====
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function teachingAssignments()
    {
        return $this->hasMany(TeachingAssignment::class, 'teacher_id');
    }

    // Materie che il docente PUÒ insegnare (competenze, non cattedre).
    public function teachableSubjects()
    {
        return $this->belongsToMany(Subject::class, 'professor_subjects', 'teacher_id', 'subject_id')
            ->withPivot('school_id')->withTimestamps();
    }

    public function moduleProgress()
    {
        return $this->hasMany(StudentModuleProgress::class);
    }

    public function conversationsAsStudent()
    {
        return $this->hasMany(Conversation::class, 'student_id');
    }

    public function conversationsAsInstructor()
    {
        return $this->hasMany(Conversation::class, 'instructor_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function chatConversations()
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function instructorNotes()
    {
        return $this->hasMany(InstructorNote::class, 'instructor_id');
    }

    public function documents()
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function conceptMapForks()
    {
        return $this->hasMany(StudentConceptMap::class);
    }

    // ===== Schola =====

    // Come docente (role=professor)
    public function schoolClassesAsTeacher()
    {
        return $this->hasMany(SchoolClass::class, 'teacher_id');
    }

    public function teachingDocuments()
    {
        return $this->hasMany(TeachingDocument::class, 'teacher_id');
    }

    public function teachingArtifacts()
    {
        return $this->hasMany(TeachingArtifact::class, 'teacher_id');
    }

    // Come studente di classe
    public function classEnrollments()
    {
        return $this->hasMany(ClassStudent::class, 'student_id');
    }

    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_students', 'student_id', 'school_class_id')
            ->withPivot('status', 'approved_at')
            ->withTimestamps();
    }
}
