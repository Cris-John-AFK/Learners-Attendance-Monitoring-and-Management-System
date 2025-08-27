<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index()
    {
        return Student::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'gradelevel' => 'required|string',
            'section' => 'required|string',
            'studentid' => 'required|string|unique:students',
            'student_id' => 'required|string|unique:students',
            'email' => 'nullable|email',
            'gender' => 'nullable|string',
            'birthdate' => 'nullable|date',
            'lrn' => 'nullable|string'
        ]);

        $student = Student::create($request->all());
        return response()->json($student, 201);
    }

    public function show(Student $student)
    {
        return $student;
    }

    public function update(Request $request, Student $student)
    {
        $request->validate([
            'name' => 'required|string',
            'gradelevel' => 'required|string',
            'section' => 'required|string',
            'email' => 'nullable|email',
            'gender' => 'nullable|string',
            'birthdate' => 'nullable|date',
            'lrn' => 'nullable|string'
        ]);

        $student->update($request->all());
        return response()->json($student);
    }

    public function destroy(Student $student)
    {
        $student->delete();
        return response()->json(['message' => 'Student deleted']);
    }

    public function byGrade($gradeLevel)
    {
        return Student::where('gradelevel', $gradeLevel)->get();
    }

    public function bySection($gradeLevel, $section)
    {
        return Student::where('gradelevel', $gradeLevel)
                      ->where('section', $section)
                      ->get();
    }
}
