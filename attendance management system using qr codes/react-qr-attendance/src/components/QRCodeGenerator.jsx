import { useEffect, useState } from 'react';
import { QRCode } from 'qrcode.react';

function getToday() {
  const today = new Date();
  return today.toISOString().slice(0, 10);
}

function getNow() {
  const now = new Date();
  return now.toTimeString().slice(0, 5);
}

function QRCodeGenerator({ subjects, sections, session, onGenerateSession }) {
  const [subject, setSubject] = useState(subjects[0] || '');
  const [section, setSection] = useState(sections[0] || '');
  const [date, setDate] = useState(getToday());
  const [time, setTime] = useState(getNow());

  useEffect(() => {
    setSubject(subjects[0] || '');
  }, [subjects]);

  useEffect(() => {
    setSection(sections[0] || '');
  }, [sections]);

  const handleGenerate = () => {
    if (!subject || !section) return;
    onGenerateSession({ subject, section, date, time });
  };

  return (
    <div className="panel-card wide-card">
      <div className="panel-row space-between">
        <div>
          <h2>QR Code Generator</h2>
          <p>Create a one-time attendance QR session for your class.</p>
        </div>
      </div>

      <div className="form-grid two-column">
        <label>
          Subject
          <select value={subject} onChange={(e) => setSubject(e.target.value)}>
            {subjects.map((subjectOption) => (
              <option key={subjectOption} value={subjectOption}>
                {subjectOption}
              </option>
            ))}
          </select>
        </label>
        <label>
          Section
          <select value={section} onChange={(e) => setSection(e.target.value)}>
            {sections.map((sectionOption) => (
              <option key={sectionOption} value={sectionOption}>
                {sectionOption}
              </option>
            ))}
          </select>
        </label>
        <label>
          Date
          <input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
        </label>
        <label>
          Time
          <input type="time" value={time} onChange={(e) => setTime(e.target.value)} />
        </label>
      </div>
      <div className="button-row">
        <button className="primary-button" onClick={handleGenerate}>
          Generate QR Code
        </button>
      </div>

      {session && (
        <div className="qr-card">
          <h3>Active QR Session</h3>
          <p>
            {session.subject} • {session.section} • {session.date} {session.time}
          </p>
          <div className="qr-wrap">
            <QRCode value={session.code} size={220} level="H" />
          </div>
        </div>
      )}
    </div>
  );
}

export default QRCodeGenerator;
