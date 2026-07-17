import { useEffect, useMemo, useState } from 'react';
import QRCodeScanner from './QRCodeScanner';
import AttendanceTable from './AttendanceTable';

const STORAGE_PROFILE = 'qrAttendance_studentProfile';

function loadProfile() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_PROFILE)) || { studentName: '', studentId: '' };
  } catch {
    return { studentName: '', studentId: '' };
  }
}

function StudentDashboard({ currentUser, session, attendance, onLogout, onAddAttendance }) {
  const [profile, setProfile] = useState(loadProfile);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    localStorage.setItem(STORAGE_PROFILE, JSON.stringify(profile));
  }, [profile]);

  const handleScanSuccess = (payload) => {
    if (!session) {
      setError('No active QR session is available. Please ask the admin to generate a valid QR code.');
      return;
    }
    if (!profile.studentName || !profile.studentId) {
      setError('Enter your full name and student ID before scanning.');
      return;
    }
    if (payload.sessionId !== session.sessionId || payload.subject !== session.subject || payload.section !== session.section) {
      setError('This QR code is invalid or has been expired by the admin.');
      return;
    }

    const record = {
      studentUsername: currentUser.username,
      studentName: profile.studentName,
      studentId: profile.studentId,
      subject: payload.subject,
      section: payload.section,
      date: payload.date,
      time: payload.time,
      recordedAt: new Date().toLocaleString(),
    };

    const duplicate = attendance.some(
      (item) =>
        item.studentId === record.studentId &&
        item.subject === record.subject &&
        item.date === record.date
    );

    if (duplicate) {
      setError('You already recorded attendance for this subject and date.');
      return;
    }

    onAddAttendance(record);
    setMessage(`Attendance recorded for ${record.subject} on ${record.date}.`);
    setError('');
  };

  const profileValid = profile.studentName.trim() && profile.studentId.trim();

  const summaryText = useMemo(() => {
    if (!session) return 'No active QR session is available.';
    return `Scan the active code for ${session.subject} - ${session.section} (${session.date} ${session.time})`;
  }, [session]);

  return (
    <div className="page student-page">
      <header className="page-header">
        <div>
          <h1>Student Dashboard</h1>
          <p>Welcome, {currentUser.username}</p>
        </div>
        <button className="ghost-button" onClick={onLogout}>
          Logout
        </button>
      </header>

      <section className="panel simple-panel">
        <div className="panel-card">
          <h2>Student Profile</h2>
          <div className="form-grid">
            <label>
              Full Name
              <input value={profile.studentName} onChange={(e) => setProfile({ ...profile, studentName: e.target.value })} />
            </label>
            <label>
              Student ID
              <input value={profile.studentId} onChange={(e) => setProfile({ ...profile, studentId: e.target.value })} />
            </label>
          </div>
          <p className="text-muted">{profileValid ? 'Profile saved locally.' : 'Fill in both fields before scanning.'}</p>
        </div>
      </section>

      <section className="panel simple-panel">
        <div className="panel-card">
          <h2>QR Attendance Scan</h2>
          <p>{summaryText}</p>
          <QRCodeScanner onScanSuccess={handleScanSuccess} onError={(message) => setError(message)} />
          {message && <div className="success-box">{message}</div>}
          {error && <div className="error-box">{error}</div>}
        </div>
      </section>

      <section className="panel">
        <AttendanceTable records={attendance} showHeader={false} />
      </section>
    </div>
  );
}

export default StudentDashboard;
