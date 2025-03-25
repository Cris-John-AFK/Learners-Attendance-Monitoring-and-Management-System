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
            'name' => 'required',
            'gradeLevel' => 'required|integer',
            'section' => 'required',
            'studentId' => 'required|unique:students'
        ]);

        return Student::create($request->all());
    }

    public function show(Student $student)
    {
        return $student;
    }

    public function update(Request $request, Student $student)
    {
        $request->validate([
            'name' => 'required',
            'gradeLevel' => 'required|integer',
            'section' => 'required'
        ]);

        $student->update($request->all());
        return $student;
    }

    public function destroy(Student $student)
    {
        $student->delete();
        return response()->json(['message' => 'Student deleted']);
    }

    public function byGrade($gradeLevel)
    {
        return Student::where('gradeLevel', $gradeLevel)->get();
    }

    public function bySection($gradeLevel, $section)
    {
        return Student::where('gradeLevel', $gradeLevel)
                      ->where('section', $section)
                      ->get();
    }
}
