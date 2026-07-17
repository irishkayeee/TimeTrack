import { useState } from 'react';

const STORAGE_USERS = 'qrAttendance_users';

const loadUsers = () => {
  const raw = localStorage.getItem(STORAGE_USERS);
  if (!raw) return [];
  try {
    return JSON.parse(raw);
  } catch {
    return [];
  }
};

function Login({ onLogin }) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  const handleSubmit = (event) => {
    event.preventDefault();
    const users = loadUsers();
    const match = users.find((user) => user.username === username && user.password === password);
    if (!match) {
      setError('Invalid username or password.');
      return;
    }
    localStorage.setItem('qrAttendance_currentUser', JSON.stringify(match));
    onLogin(match);
  };

  return (
    <div className="page login-page">
      <div className="card login-card">
        <h1>School QR Attendance</h1>
        <p className="subtitle">Login as Admin or Student</p>
        <form onSubmit={handleSubmit} className="login-form">
          <label>
            Username
            <input value={username} onChange={(e) => setUsername(e.target.value)} placeholder="admin or student" />
          </label>
          <label>
            Password
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="admin123 or student123"
            />
          </label>
          {error && <div className="error-box">{error}</div>}
          <button type="submit" className="primary-button">
            Sign In
          </button>
        </form>
        <div className="hint-box">
          Admin: <strong>admin</strong> / admin123<br />
          Student: <strong>student</strong> / student123
        </div>
      </div>
    </div>
  );
}

export default Login;
