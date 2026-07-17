function AttendanceTable({ records, showHeader }) {
  return (
    <div className="panel-card">
      <div className="table-title">
        <h2>{showHeader ? 'Attendance Records' : 'My Attendance History'}</h2>
      </div>
      {records.length === 0 ? (
        <div className="empty-state">No attendance records available yet.</div>
      ) : (
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Student</th>
                <th>ID</th>
                <th>Subject</th>
                <th>Section</th>
              </tr>
            </thead>
            <tbody>
              {records.map((record, index) => (
                <tr key={`${record.studentId}-${record.date}-${record.subject}-${index}`}>
                  <td>{record.date}</td>
                  <td>{record.time}</td>
                  <td>{record.studentName}</td>
                  <td>{record.studentId}</td>
                  <td>{record.subject}</td>
                  <td>{record.section}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

export default AttendanceTable;
