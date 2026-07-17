import { useEffect, useMemo, useState } from 'react';
import Login from './components/Login';
import AdminDashboard from './components/AdminDashboard';
import StudentDashboard from './components/StudentDashboard';

const STORAGE_KEYS = {
  currentUser: 'qrAttendance_currentUser',
  subjects: 'qrAttendance_subjects',
  sections: 'qrAttendance_sections',
  attendance: 'qrAttendance_records',
  session: 'qrAttendance_session',
};

const defaultUsers = [
  { username: 'admin', password: 'admin123', role: 'admin' },
  { username: 'student', password: 'student123', role: 'student' },
];

function loadFromStorage(key, fallback) {
  const raw = localStorage.getItem(key);
  if (!raw) return fallback;
  try {
    return JSON.parse(raw);
  } catch (error) {
    return fallback;
  }
}

function saveToStorage(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}

function App() {
  const [currentUser, setCurrentUser] = useState(() => loadFromStorage(STORAGE_KEYS.currentUser, null));
  const [subjects, setSubjects] = useState(() => loadFromStorage(STORAGE_KEYS.subjects, ['Mathematics']));
  const [sections, setSections] = useState(() => loadFromStorage(STORAGE_KEYS.sections, ['Section A']));
  const [attendance, setAttendance] = useState(() => loadFromStorage(STORAGE_KEYS.attendance, []));
  const [session, setSession] = useState(() => loadFromStorage(STORAGE_KEYS.session, null));

  useEffect(() => {
    if (!loadFromStorage('qrAttendance_users', null)) {
      saveToStorage('qrAttendance_users', defaultUsers);
    }
  }, []);

  useEffect(() => saveToStorage(STORAGE_KEYS.currentUser, currentUser), [currentUser]);
  useEffect(() => saveToStorage(STORAGE_KEYS.subjects, subjects), [subjects]);
  useEffect(() => saveToStorage(STORAGE_KEYS.sections, sections), [sections]);
  useEffect(() => saveToStorage(STORAGE_KEYS.attendance, attendance), [attendance]);
  useEffect(() => saveToStorage(STORAGE_KEYS.session, session), [session]);

  const login = (user) => setCurrentUser(user);
  const logout = () => setCurrentUser(null);

  const addSubject = (subject) => {
    if (!subject || subjects.includes(subject)) return;
    setSubjects((current) => [...current, subject]);
  };

  const addSection = (section) => {
    if (!section || sections.includes(section)) return;
    setSections((current) => [...current, section]);
  };

  const generateSession = (data) => {
    const code = JSON.stringify({
      subject: data.subject,
      section: data.section,
      date: data.date,
      time: data.time,
      sessionId: `${data.subject}_${data.section}_${Date.now()}`,
    });
    setSession({ ...data, code, generatedAt: new Date().toISOString() });
  };

  const clearSession = () => setSession(null);

  const addAttendance = (record) => {
    setAttendance((current) => [...current, record]);
  };

  const clearAttendance = () => setAttendance([]);

  const userAttendance = useMemo(() => {
    if (!currentUser) return [];
    if (currentUser.role === 'admin') return attendance;
    return attendance.filter((record) => record.studentUsername === currentUser.username);
  }, [attendance, currentUser]);

  if (!currentUser) {
    return <Login onLogin={login} />;
  }

  return (
    <div className="app-shell">
      {currentUser.role === 'admin' ? (
        <AdminDashboard
          currentUser={currentUser}
          subjects={subjects}
          sections={sections}
          session={session}
          attendance={attendance}
          userAttendance={userAttendance}
          onLogout={logout}
          onAddSubject={addSubject}
          onAddSection={addSection}
          onGenerateSession={generateSession}
          onClearSession={clearSession}
          onClearAttendance={clearAttendance}
        />
      ) : (
        <StudentDashboard
          currentUser={currentUser}
          session={session}
          attendance={userAttendance}
          onLogout={logout}
          onAddAttendance={addAttendance}
        />
      )}
    </div>
  );
}

export default App;
