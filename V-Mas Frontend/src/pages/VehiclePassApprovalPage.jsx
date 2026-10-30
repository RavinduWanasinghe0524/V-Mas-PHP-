import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { vehiclePassAPI } from '../services/api'

export default function VehiclePassApprovalPage() {
  const [params] = useSearchParams()
  const token = params.get('token') || ''
  const [pass, setPass] = useState(null)
  const [message, setMessage] = useState('Loading application…')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!token) { setMessage('Approval link is missing.'); return }
    vehiclePassAPI.getApproval(token).then(r => { setPass(r.data?.data); setMessage('') }).catch(e => setMessage(e.response?.data?.message || 'Unable to load application.'))
  }, [token])

  async function decide(decision) {
    setBusy(true)
    try {
      const r = await vehiclePassAPI.decideApproval(token, decision)
      setMessage(`${r.data?.message || 'Decision recorded'}. Current status: ${r.data?.data?.status || 'updated'}`)
      setPass(null)
    } catch (e) { setMessage(e.response?.data?.message || 'Unable to record decision.') }
    finally { setBusy(false) }
  }

  return <main style={{ minHeight: '100vh', padding: '8vh 20px', background: '#f4f7fc', fontFamily: 'sans-serif' }}>
    <section style={{ maxWidth: 540, margin: 'auto', padding: 30, borderRadius: 20, background: '#fff', boxShadow: '0 12px 40px #18336d18' }}>
      <h1 style={{ color: '#18336d' }}>Vehicle pass approval</h1>
      {pass ? <>
        <p><strong>Pass:</strong> {pass.passCode}</p><p><strong>Applicant:</strong> {pass.applicantName}</p>
        <p><strong>Faculty:</strong> {pass.faculty}</p><p><strong>Department:</strong> {pass.department}</p>
        <p><strong>Vehicle:</strong> {pass.vehicleRegNumber}</p>
        <p><strong>Review stage:</strong> {pass.status === 'PENDING_HOD' ? 'Head of Department' : 'Dean'}</p>
        <div style={{ display: 'flex', gap: 12 }}>
          <button disabled={busy} onClick={() => decide('APPROVE')} style={{ padding: '12px 20px', background: '#2456d7', color: '#fff', border: 0, borderRadius: 10 }}>Approve</button>
          <button disabled={busy} onClick={() => decide('REJECT')} style={{ padding: '12px 20px', background: '#fff', color: '#bd2634', border: '1px solid #bd2634', borderRadius: 10 }}>Reject</button>
        </div>
      </> : <p role="status">{message}</p>}
    </section>
  </main>
}
