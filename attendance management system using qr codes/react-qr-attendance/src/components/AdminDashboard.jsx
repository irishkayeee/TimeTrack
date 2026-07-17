import { useMemo, useState } from 'react';
import QRCodeGenerator from './QRCodeGenerator';
import AttendanceTable from './AttendanceTable';

function AdminDashboard({
  currentUser,
  subjects,
  sections,
  session,
  attendance,
  userAttendance,
  onLogout,
  onAddSubject,
  onAddSection,
  onGenerateSession,
  onClearSession,
  onClearAttendance,
}) {
  const [subjectInput, setSubjectInput] = useState('');
  const [sectionInput, setSectionInput] = useState('');

  const sessionSummary = useMemo(() => {
    if (!session) return 'No active QR session generated.';
    return `${session.subject} • ${session.section} • ${session.date} ${session.time}`;
  }, [session]);

  return (
    <div className="page admin-page">
      <header className="page-header">
        <div>
          <h1>Admin Dashboard</h1>
          <p>Welcome back, {currentUser.username}</p>
        </div>
        <button className="ghost-button" onClick={onLogout}>
          Logout
        </button>
      </header>

      <section className="panel">
        <div className="panel-row">
          <div className="panel-card">
            <h2>Session Status</h2>
            <p>{sessionSummary}</p>
            <div className="button-row">
              <button className="secondary-button" onClick={onClearSession}>
                Clear Session
              </button>
            </div>
          </div>
          <div className="panel-card">
            <h2>Attendance Records</h2>
            <p>{attendance.length} total records</p>
            <button className="secondary-button" onClick={onClearAttendance}>
              Clear All Attendance
            </button>
          </div>
        </div>
      </section>

      <section className="panel two-column">
        <div className="panel-card small-card">
          <h2>Add Subject</h2>
          <div className="form-group">
            <input value={subjectInput} onChange={(e) => setSubjectInput(e.target.value)} placeholder="New subject name" />
            <button
              className="primary-button"
              onClick={() => {
                if (!subjectInput.trim()) return;
                onAddSubject(subjectInput.trim());
                setSubjectInput('');
              }}
            >
              Add Subject
            </button>
          </div>
          <div className="tag-list">{subjects.map((subject) => <span key={subject} className="tag">{subject}</span>)}</div>
        </div>

        <div className="panel-card small-card">
          <h2>Add Section</h2>
          <div className="form-group">
            <input value={sectionInput} onChange={(e) => setSectionInput(e.target.value)} placeholder="New section name" />
            <button
              className="primary-button"
              onClick={() => {
                if (!sectionInput.trim()) return;
                onAddSection(sectionInput.trim());
                setSectionInput('');
              }}
            >
              Add Section
            </button>
          </div>
          <div className="tag-list">{sections.map((section) => <span key={section} className="tag">{section}</span>)}</div>
        </div>
      </section>

      <section className="panel">
        <QRCodeGenerator
          subjects={subjects}
          sections={sections}
          session={session}
          onGenerateSession={onGenerateSession}
        />
      </section>

      <section className="panel">
        <AttendanceTable records={userAttendance} showHeader={true} />
      </section>
    </div>
  );
}

export default AdminDashboard;
