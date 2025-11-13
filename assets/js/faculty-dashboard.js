// Faculty Dashboard JavaScript
class FacultyDashboard {
    constructor() {
        this.currentFaculty = null;
        this.init();
    }

    init() {
        this.loadFacultyData();
        this.setupNavigation();
        this.setupEventListeners();
        this.loadCourses();
        this.loadSessions();
    }

    loadFacultyData() {
        // Simulate loading faculty data from API
        this.currentFaculty = {
            faculty_id: 'F12345',
            first_name: 'Sarah',
            last_name: 'Johnson',
            email: 'sarah.johnson@university.edu',
            dob: '1975-08-20',
            department_id: 1,
            department_name: 'Computer Science',
            designation: 'Associate Professor'
        };

        this.updateFacultyInfo();
    }

    updateFacultyInfo() {
        document.getElementById('facultyName').textContent = 
            `${this.currentFaculty.first_name} ${this.currentFaculty.last_name}`;
        document.getElementById('facultyId').textContent = this.currentFaculty.faculty_id;
        document.getElementById('facultyDepartment').textContent = this.currentFaculty.department_name;
        document.getElementById('facultyDesignation').textContent = this.currentFaculty.designation;

        // Update profile form
        document.getElementById('profileFirstName').value = this.currentFaculty.first_name;
        document.getElementById('profileLastName').value = this.currentFaculty.last_name;
        document.getElementById('profileEmail').value = this.currentFaculty.email;
        document.getElementById('profileDob').value = this.currentFaculty.dob;
        document.getElementById('profileDesignation').value = this.currentFaculty.designation;
    }

    setupNavigation() {
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetPage = link.getAttribute('data-page');
                this.showSection(targetPage);
                
                // Update active state
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            });
        });
    }

    showSection(sectionName) {
        // Hide all sections
        document.querySelectorAll('.dashboard-section').forEach(section => {
            section.style.display = 'none';
        });

        // Show target section
        const targetSection = document.getElementById(`${sectionName}-section`);
        if (targetSection) {
            targetSection.style.display = 'block';
        }
    }

    setupEventListeners() {
        // Session management
        document.getElementById('createSessionBtn').addEventListener('click', () => {
            this.toggleSessionForm();
        });

        document.getElementById('cancelSessionBtn').addEventListener('click', () => {
            this.toggleSessionForm();
        });

        document.getElementById('takeAttendanceBtn').addEventListener('click', () => {
            this.takeAttendance();
        });

        // Session form submission
        document.getElementById('sessionForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.createSession();
        });

        // Reports
        document.getElementById('generateReportBtn').addEventListener('click', () => {
            this.generateReport();
        });

        // Profile form submission
        document.getElementById('profileForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.updateProfile();
        });
    }

    loadCourses() {
        // Simulate API call to get faculty courses
        const courses = [
            { 
                course_id: 1, 
                course_code: 'CS 301', 
                course_name: 'Web Technology',
                enrolled_students: 45,
                total_sessions: 15
            },
            { 
                course_id: 2, 
                course_code: 'CS 401', 
                course_name: 'Advanced Web Development',
                enrolled_students: 32,
                total_sessions: 12
            }
        ];

        const container = document.getElementById('coursesContainer');
        container.innerHTML = '';

        courses.forEach(course => {
            const courseElement = document.createElement('div');
            courseElement.className = 'course-item';
            courseElement.innerHTML = `
                <h3>${course.course_code} - ${course.course_name}</h3>
                <p>Enrolled Students: ${course.enrolled_students}</p>
                <p>Total Sessions: ${course.total_sessions}</p>
                <div class="course-actions">
                    <button class="action-btn small view-course-btn" data-course-id="${course.course_id}">
                        View Details
                    </button>
                    <button class="action-btn small manage-students-btn" data-course-id="${course.course_id}">
                        Manage Students
                    </button>
                </div>
            `;
            container.appendChild(courseElement);
        });

        // Populate session course dropdown
        const sessionCourseSelect = document.getElementById('sessionCourse');
        sessionCourseSelect.innerHTML = '<option value="">Select Course</option>';
        courses.forEach(course => {
            const option = document.createElement('option');
            option.value = course.course_id;
            option.textContent = `${course.course_code} - ${course.course_name}`;
            sessionCourseSelect.appendChild(option);
        });

        // Populate report course dropdown
        const reportCourseSelect = document.getElementById('reportCourse');
        reportCourseSelect.innerHTML = '<option value="">All Courses</option>';
        courses.forEach(course => {
            const option = document.createElement('option');
            option.value = course.course_id;
            option.textContent = `${course.course_code} - ${course.course_name}`;
            reportCourseSelect.appendChild(option);
        });
    }

    loadSessions() {
        // Simulate API call to get sessions
        const sessions = [
            {
                session_id: 1,
                course_code: 'CS 301',
                topic: 'Introduction to HTML & CSS',
                date: '2024-10-07',
                start_time: '10:30',
                end_time: '12:00',
                location: 'Room 101',
                attendance: '42/45'
            }
        ];

        const container = document.getElementById('upcomingSessions');
        container.innerHTML = '';

        sessions.forEach(session => {
            const sessionElement = document.createElement('div');
            sessionElement.className = 'session-item';
            sessionElement.innerHTML = `
                <div class="session-info">
                    <h4>${session.course_code} - ${session.topic}</h4>
                    <p>Date: ${this.formatDate(session.date)} | Time: ${this.formatTime(session.start_time)} - ${this.formatTime(session.end_time)}</p>
                    <p>Location: ${session.location} | Attendance: ${session.attendance}</p>
                </div>
                <div class="session-actions">
                    <button class="action-btn small take-attendance-btn" data-session-id="${session.session_id}">
                        Take Attendance
                    </button>
                    <button class="action-btn small edit-session-btn" data-session-id="${session.session_id}">
                        Edit
                    </button>
                </div>
            `;
            container.appendChild(sessionElement);
        });
    }

    toggleSessionForm() {
        const form = document.getElementById('createSessionForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }

    createSession() {
        const formData = new FormData(document.getElementById('sessionForm'));
        const sessionData = {
            course_id: formData.get('course_id'),
            topic: formData.get('topic'),
            date: formData.get('date'),
            start_time: formData.get('start_time'),
            end_time: formData.get('end_time'),
            location: formData.get('location')
        };

        if (this.validateSession(sessionData)) {
            // Simulate API call to create session
            console.log('Creating session:', sessionData);
            alert('Session created successfully!');
            document.getElementById('sessionForm').reset();
            this.toggleSessionForm();
            this.loadSessions(); // Refresh sessions list
        }
    }

    validateSession(data) {
        const errors = [];

        if (!data.course_id) {
            errors.push('Course selection is required');
        }

        if (!data.topic.trim()) {
            errors.push('Topic is required');
       