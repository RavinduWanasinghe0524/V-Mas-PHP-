import { useEffect, useState } from 'react'
import Sidebar from '../components/Sidebar'
import Topbar from '../components/Topbar'
import { useAuth } from '../context/AuthContext'
import { useD, useTheme } from '../context/ThemeContext'
import { vehiclePassAPI } from '../services/api'
import {
  Ticket, CheckCircle, XCircle, Clock, Search, Printer,
  RefreshCw, Check, X, Shield, Filter, AlertCircle, Trash2,
  Car, User, Phone, MapPin, Calendar, FileText
} from 'lucide-react'

const VehiclePassAdminPage = () => {
  const { user } = useAuth()
  const D = useD()
  const { theme } = useTheme()
  const isDark = theme === 'blue'

  const [passes, setPasses] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [statusFilter, setStatusFilter] = useState('ALL')
  const [formTypeFilter, setFormTypeFilter] = useState('ALL')
  const [searchTerm, setSearchTerm] = useState('')

  // Reject modal state
  const [rejectModalOpen, setRejectModalOpen] = useState(false)
  const [selectedPass, setSelectedPass] = useState(null)
  const [rejectionReason, setRejectionReason] = useState('')
  const [processingId, setProcessingId] = useState(null)

  // Fetch Passes
  const fetchPasses = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await vehiclePassAPI.getAll()
      if (res.data?.success && Array.isArray(res.data?.data)) {
        setPasses(res.data.data)
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load vehicle pass applications')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchPasses()
  }, [])

  // Approve Pass
  const handleApprove = async (pass) => {
    setProcessingId(pass.id)
    try {
      await vehiclePassAPI.updateStatus(pass.id, { status: 'APPROVED' })
      await fetchPasses()
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to approve pass')
    } finally {
      setProcessingId(null)
    }
  }
  const handleResendApproval = async (pass) => {
    setProcessingId(pass.id)
    try { await vehiclePassAPI.resendApproval(pass.id); alert('Approval email sent') }
    catch (err) { alert(err.response?.data?.message || 'Unable to send approval email') }
    finally { setProcessingId(null) }
  }

  // Open Reject Modal
  const openRejectModal = (pass) => {
    setSelectedPass(pass)
    setRejectionReason('')
    setRejectModalOpen(true)
  }

  // Confirm Reject
  const handleConfirmReject = async () => {
    if (!selectedPass) return
    setProcessingId(selectedPass.id)
    try {
      await vehiclePassAPI.updateStatus(selectedPass.id, {
        status: 'REJECTED',
        rejectionReason: rejectionReason.trim() || 'Declined by administration'
      })
      setRejectModalOpen(false)
      setSelectedPass(null)
      await fetchPasses()
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to reject pass')
    } finally {
      setProcessingId(null)
    }
  }

  // Delete Pass (Admin only)
  const handleDelete = async (id) => {
    if (!window.confirm('Are you sure you want to delete this pass application?')) return
    try {
      await vehiclePassAPI.delete(id)
      await fetchPasses()
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to delete pass application')
    }
  }

  // Print single pass slip
  const handlePrintSlip = (pass) => {
    const isOutstation = pass.formType === 'SUSL/GA/TD/01'
    const printWindow = window.open('', '_blank')
    printWindow.document.write(`
      <html>
        <head>
          <title>${pass.formType || 'Pass'} - ${pass.passCode}</title>
          <style>
            body { font-family: sans-serif; padding: 30px; color: #1e293b; background: #fff; }
            .card { border: 2px solid #1f3a5f; border-radius: 8px; padding: 24px; max-width: 620px; margin: 0 auto; }
            .ref { float: right; font-weight: bold; border: 1.5px solid #000; padding: 2px 8px; font-size: 13px; }
            .header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px; clear: both; }
            .uni { font-size: 18px; font-weight: bold; margin: 0; }
            .sub { font-size: 14px; color: #64748b; margin: 2px 0 10px; }
            .title { font-size: 16px; font-weight: bold; text-decoration: underline; margin: 0 0 12px; }
            .code { font-family: monospace; font-size: 22px; font-weight: bold; color: #1f3a5f; letter-spacing: 2px; }
            .row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; border-bottom: 1px dotted #e2e8f0; padding-bottom: 4px; }
            .label { font-weight: bold; color: #475569; }
            .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-weight: bold; font-size: 12px; background: #dcfce7; color: #166534; }
            .footer { margin-top: 24px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
          </style>
        </head>
        <body>
          <div class="card">
            <span class="ref">${pass.formType || 'SUSL/GA/TD/03'}</span>
            <div class="header">
              <div class="uni">Sabaragamuwa University of Sri Lanka</div>
              <div class="sub">General Administration Division</div>
              <div class="title">${isOutstation ? 'Vehicle Gate Pass for Outstation Travel' : 'Staff Vehicle Identification Permit'}</div>
              <div class="code">${pass.passCode}</div>
              <div style="margin-top: 6px;"><span class="badge">${pass.status}</span></div>
            </div>
            <div class="row"><span class="label">Applicant Name:</span><span>${pass.applicantName}</span></div>
            <div class="row"><span class="label">Phone / Contact:</span><span>${pass.phoneNumber}</span></div>
            ${pass.nicNumber ? `<div class="row"><span class="label">NIC / ID:</span><span>${pass.nicNumber}</span></div>` : ''}
            <div class="row"><span class="label">Vehicle Registration:</span><span style="font-weight:bold;">${pass.vehicleRegNumber}</span></div>
            <div class="row"><span class="label">Vehicle Type:</span><span>${pass.vehicleType}</span></div>
            <div class="row"><span class="label">Valid Dates:</span><span>${pass.startDate} ${pass.endDate ? 'to ' + pass.endDate : ''}</span></div>
            <div class="row"><span class="label">Department / Destination:</span><span>${pass.destination || 'University Premises'}</span></div>
            <div class="row"><span class="label">Purpose / Notes:</span><span>${pass.purpose}</span></div>
            <div class="row"><span class="label">Authorized By:</span><span>${pass.approvedBy || 'Security Controller'}</span></div>
            <div class="footer">Sabaragamuwa University of Sri Lanka • Official Gate Entry Document</div>
          </div>
          <script>window.onload = function() { window.print(); }<\/script>
        </body>
      </html>
    `)
    printWindow.document.close()
  }

  // Filtered passes
  const filteredPasses = passes.filter(p => {
    const matchesStatus = statusFilter === 'ALL' || p.status === statusFilter
    const matchesType = formTypeFilter === 'ALL' || (p.formType || 'SUSL/GA/TD/03') === formTypeFilter
    const term = searchTerm.toLowerCase()
    const matchesSearch = !term ||
      (p.applicantName?.toLowerCase().includes(term)) ||
      (p.vehicleRegNumber?.toLowerCase().includes(term)) ||
      (p.passCode?.toLowerCase().includes(term)) ||
      (p.phoneNumber?.toLowerCase().includes(term)) ||
      (p.destination?.toLowerCase().includes(term))
    return matchesStatus && matchesType && matchesSearch
  })

  // Stat counts
  const totalCount = passes.length
  const pendingCount = passes.filter(p => p.status?.startsWith('PENDING')).length
  const approvedCount = passes.filter(p => p.status === 'APPROVED').length
  const rejectedCount = passes.filter(p => p.status === 'REJECTED').length


  return (
    <div style={{ display: 'flex', minHeight: '100vh', background: D.bg, color: D.text }}>
      <Sidebar />

      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0, paddingLeft: 'var(--sidebar-w, 290px)' }}>
        <Topbar />

        <div style={{ padding: '2rem', maxWidth: 1400, width: '100%', margin: '0 auto', boxSizing: 'border-box' }}>
          {/* Header */}
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 16, marginBottom: '2rem' }}>
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 6 }}>
                <div style={{ width: 36, height: 36, borderRadius: 10, background: 'rgba(37, 99, 235, 0.15)', color: '#3b82f6', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                  <Ticket size={20} />
                </div>
                <h1 style={{ fontSize: '1.75rem', fontWeight: 800, margin: 0, letterSpacing: '-0.02em' }}>
                  Vehicle Pass Management
                </h1>
              </div>
              <p style={{ color: D.textSub, margin: 0, fontSize: '0.92rem' }}>
                Review and approve public, visitor, contractor, and staff gate access requests.
              </p>
            </div>

            <button
              onClick={fetchPasses}
              disabled={loading}
              style={{
                display: 'inline-flex', alignItems: 'center', gap: 8,
                padding: '0.65rem 1.2rem', borderRadius: 10,
                background: D.surface, border: `1px solid ${D.border}`,
                color: D.text, fontWeight: 600, fontSize: '0.88rem', cursor: 'pointer'
              }}
            >
              <RefreshCw size={15} className={loading ? 'animate-spin' : ''} />
              <span>Refresh</span>
            </button>
          </div>

          {/* Stat Cards */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1.2rem', marginBottom: '2rem' }}>
            <div style={{ background: D.surface, border: `1px solid ${D.border}`, borderRadius: 16, padding: '1.25rem 1.5rem' }}>
              <span style={{ fontSize: '0.78rem', fontWeight: 700, color: D.textSub, textTransform: 'uppercase', letterSpacing: '0.06em' }}>Total Requests</span>
              <div style={{ fontSize: '1.9rem', fontWeight: 800, marginTop: 4, color: D.text }}>{totalCount}</div>
            </div>

            <div style={{ background: D.surface, border: `1px solid rgba(245, 158, 11, 0.3)`, borderRadius: 16, padding: '1.25rem 1.5rem' }}>
              <span style={{ fontSize: '0.78rem', fontWeight: 700, color: '#f59e0b', textTransform: 'uppercase', letterSpacing: '0.06em' }}>Pending Review</span>
              <div style={{ fontSize: '1.9rem', fontWeight: 800, marginTop: 4, color: '#f59e0b' }}>{pendingCount}</div>
            </div>

            <div style={{ background: D.surface, border: `1px solid rgba(16, 185, 129, 0.3)`, borderRadius: 16, padding: '1.25rem 1.5rem' }}>
              <span style={{ fontSize: '0.78rem', fontWeight: 700, color: '#10b981', textTransform: 'uppercase', letterSpacing: '0.06em' }}>Approved Passes</span>
              <div style={{ fontSize: '1.9rem', fontWeight: 800, marginTop: 4, color: '#10b981' }}>{approvedCount}</div>
            </div>

            <div style={{ background: D.surface, border: `1px solid rgba(239, 68, 68, 0.3)`, borderRadius: 16, padding: '1.25rem 1.5rem' }}>
              <span style={{ fontSize: '0.78rem', fontWeight: 700, color: '#ef4444', textTransform: 'uppercase', letterSpacing: '0.06em' }}>Rejected</span>
              <div style={{ fontSize: '1.9rem', fontWeight: 800, marginTop: 4, color: '#ef4444' }}>{rejectedCount}</div>
            </div>
          </div>

          {/* Filters & Search */}
          <div style={{
            display: 'flex', justifyContent: 'space-between', alignItems: 'center',
            flexWrap: 'wrap', gap: 14, background: D.surface,
            padding: '1rem 1.4rem', borderRadius: 14, border: `1px solid ${D.border}`,
            marginBottom: '1.5rem'
          }}>
            {/* Status Filter Tabs */}
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
              {['ALL', 'PENDING_HOD', 'PENDING_DEAN', 'APPROVED', 'REJECTED'].map(st => (
                <button
                  key={st}
                  onClick={() => setStatusFilter(st)}
                  style={{
                    padding: '0.5rem 1rem', borderRadius: 8,
                    fontSize: '0.82rem', fontWeight: 700, border: 'none', cursor: 'pointer',
                    background: statusFilter === st ? '#2563eb' : 'transparent',
                    color: statusFilter === st ? '#fff' : D.textSub,
                    transition: 'all 0.2s'
                  }}
                >
                  {st} {st === 'PENDING' && pendingCount > 0 ? `(${pendingCount})` : ''}
                </button>
              ))}
            </div>

            {/* Form Type Filter Tabs */}
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
              <span style={{ fontSize: '0.76rem', color: D.textSub, fontWeight: 700 }}>FORM:</span>
              {[
                { id: 'ALL', label: 'All' },
                { id: 'SUSL/GA/TD/03', label: 'Form 03 (Staff ID)' },
                { id: 'SUSL/GA/TD/01', label: 'Form 01 (Outstation)' }
              ].map(ft => (
                <button
                  key={ft.id}
                  onClick={() => setFormTypeFilter(ft.id)}
                  style={{
                    padding: '0.4rem 0.8rem', borderRadius: 6,
                    fontSize: '0.78rem', fontWeight: 600,
                    border: `1px solid ${formTypeFilter === ft.id ? '#3b82f6' : D.border}`,
                    cursor: 'pointer',
                    background: formTypeFilter === ft.id ? 'rgba(59, 130, 246, 0.15)' : 'transparent',
                    color: formTypeFilter === ft.id ? '#3b82f6' : D.textSub,
                    transition: 'all 0.2s'
                  }}
                >
                  {ft.label}
                </button>
              ))}
            </div>

            {/* Search Input */}
            <div style={{ position: 'relative', minWidth: 260 }}>
              <Search size={15} style={{ position: 'absolute', left: 12, top: '50%', transform: 'translateY(-50%)', color: D.textSub }} />
              <input
                type="text"
                placeholder="Search plate, code, name..."
                value={searchTerm}
                onChange={e => setSearchTerm(e.target.value)}
                style={{
                  width: '100%', padding: '0.55rem 1rem 0.55rem 2.3rem',
                  borderRadius: 10, border: `1px solid ${D.border}`,
                  background: D.surfaceHi, color: D.text, fontSize: '0.86rem', outline: 'none'
                }}
              />
            </div>
          </div>

          {/* Passes List / Table */}
          <div style={{ background: D.surface, borderRadius: 16, border: `1px solid ${D.border}`, overflow: 'hidden' }}>
            {loading ? (
              <div style={{ padding: '4rem', textAlign: 'center', color: D.textSub }}>
                <RefreshCw size={24} className="animate-spin" style={{ margin: '0 auto 12px' }} />
                <div>Loading vehicle passes...</div>
              </div>
            ) : filteredPasses.length === 0 ? (
              <div style={{ padding: '4rem 2rem', textAlign: 'center', color: D.textSub }}>
                <Ticket size={36} style={{ margin: '0 auto 12px', opacity: 0.4 }} />
                <h3 style={{ fontSize: '1.1rem', fontWeight: 700, margin: '0 0 6px', color: D.text }}>No vehicle passes found</h3>
                <p style={{ margin: 0, fontSize: '0.88rem' }}>No applications matching your current filter criteria.</p>
              </div>
            ) : (
              <div style={{ overflowX: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: '0.88rem' }}>
                  <thead>
                    <tr style={{ borderBottom: `1px solid ${D.border}`, background: D.surfaceHi, color: D.textSub, fontSize: '0.75rem', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                      <th style={{ padding: '1rem 1.2rem' }}>Pass Code</th>
                      <th style={{ padding: '1rem 1.2rem' }}>Applicant</th>
                      <th style={{ padding: '1rem 1.2rem' }}>Vehicle</th>
                      <th style={{ padding: '1rem 1.2rem' }}>Dates</th>
                      <th style={{ padding: '1rem 1.2rem' }}>Destination & Purpose</th>
                      <th style={{ padding: '1rem 1.2rem' }}>Status</th>
                      <th style={{ padding: '1rem 1.2rem', textAlign: 'right' }}>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredPasses.map(pass => (
                      <tr key={pass.id} style={{ borderBottom: `1px solid ${D.border}` }}>
                        {/* Pass Code */}
                        <td style={{ padding: '1rem 1.2rem', whiteSpace: 'nowrap' }}>
                          <span style={{ fontFamily: 'monospace', fontWeight: 700, color: '#3b82f6', background: 'rgba(59, 130, 246, 0.1)', padding: '4px 8px', borderRadius: 6 }}>
                            {pass.passCode}
                          </span>
                          <div style={{ marginTop: 4, display: 'flex', gap: 4, alignItems: 'center' }}>
                            <span style={{ fontSize: '0.68rem', fontWeight: 700, padding: '1px 6px', borderRadius: 4, background: pass.formType === 'SUSL/GA/TD/01' ? 'rgba(168, 85, 247, 0.15)' : 'rgba(59, 130, 246, 0.15)', color: pass.formType === 'SUSL/GA/TD/01' ? '#a855f7' : '#3b82f6' }}>
                              {pass.formType || 'SUSL/GA/TD/03'}
                            </span>
                          </div>
                        </td>

                        {/* Applicant */}
                        <td style={{ padding: '1rem 1.2rem' }}>
                          <div style={{ fontWeight: 700, color: D.text }}>{pass.applicantName}</div>
                          <div style={{ fontSize: '0.78rem', color: D.textSub, marginTop: 2 }}>{pass.phoneNumber}</div>
                          {pass.email && <div style={{ fontSize: '0.74rem', color: D.textSub }}>{pass.email}</div>}
                        </td>

                        {/* Vehicle */}
                        <td style={{ padding: '1rem 1.2rem' }}>
                          <div style={{ fontWeight: 800, letterSpacing: '0.04em' }}>{pass.vehicleRegNumber}</div>
                          <div style={{ fontSize: '0.78rem', color: D.textSub }}>
                            {pass.vehicleType} {pass.vehicleModel ? `• ${pass.vehicleModel}` : ''}
                          </div>
                        </td>

                        {/* Dates */}
                        <td style={{ padding: '1rem 1.2rem', whiteSpace: 'nowrap' }}>
                          <div>{pass.startDate}</div>
                          {pass.endDate && <div style={{ fontSize: '0.75rem', color: D.textSub }}>to {pass.endDate}</div>}
                        </td>

                        {/* Purpose */}
                        <td style={{ padding: '1rem 1.2rem', maxWidth: 260 }}>
                          {pass.destination && (
                            <div style={{ fontWeight: 600, color: D.text, fontSize: '0.82rem', marginBottom: 2 }}>
                              📍 {pass.destination}
                            </div>
                          )}
                          <div style={{ fontSize: '0.8rem', color: D.textSub, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {pass.purpose}
                          </div>
                        </td>

                        {/* Status */}
                        <td style={{ padding: '1rem 1.2rem', whiteSpace: 'nowrap' }}>
                          <span style={{
                            display: 'inline-flex', alignItems: 'center', gap: 4,
                            padding: '3px 10px', borderRadius: 999, fontSize: '0.74rem', fontWeight: 800, textTransform: 'uppercase',
                            background: pass.status === 'APPROVED' ? 'rgba(16, 185, 129, 0.15)' : pass.status === 'REJECTED' ? 'rgba(239, 68, 68, 0.15)' : 'rgba(245, 158, 11, 0.15)',
                            color: pass.status === 'APPROVED' ? '#10b981' : pass.status === 'REJECTED' ? '#ef4444' : '#f59e0b',
                            border: `1px solid ${pass.status === 'APPROVED' ? 'rgba(16, 185, 129, 0.3)' : pass.status === 'REJECTED' ? 'rgba(239, 68, 68, 0.3)' : 'rgba(245, 158, 11, 0.3)'}`
                          }}>
                            {pass.status}
                          </span>
                        </td>

                        {/* Actions */}
                        <td style={{ padding: '1rem 1.2rem', textAlign: 'right', whiteSpace: 'nowrap' }}>
                          <div style={{ display: 'inline-flex', gap: 6 }}>
                            {pass.status?.startsWith('PENDING_') && <button type="button" disabled={processingId === pass.id} onClick={() => handleResendApproval(pass)} style={{ padding: '0.45rem 0.8rem', borderRadius: 8, border: `1px solid ${D.border}`, background: D.surfaceHi, color: D.text, cursor: 'pointer' }}>Resend email</button>}
                            {pass.status === 'PENDING' && (
                              <>
                                <button
                                  onClick={() => handleApprove(pass)}
                                  disabled={processingId === pass.id}
                                  title="Approve Pass"
                                  style={{
                                    display: 'inline-flex', alignItems: 'center', gap: 4,
                                    padding: '0.45rem 0.8rem', borderRadius: 8,
                                    background: '#10b981', color: '#fff', border: 'none',
                                    fontSize: '0.8rem', fontWeight: 700, cursor: 'pointer'
                                  }}
                                >
                                  <Check size={14} />
                                  <span>Approve</span>
                                </button>

                                <button
                                  onClick={() => openRejectModal(pass)}
                                  disabled={processingId === pass.id}
                                  title="Reject Pass"
                                  style={{
                                    display: 'inline-flex', alignItems: 'center', gap: 4,
                                    padding: '0.45rem 0.8rem', borderRadius: 8,
                                    background: 'rgba(239, 68, 68, 0.15)', color: '#ef4444',
                                    border: '1px solid rgba(239, 68, 68, 0.3)',
                                    fontSize: '0.8rem', fontWeight: 700, cursor: 'pointer'
                                  }}
                                >
                                  <X size={14} />
                                  <span>Reject</span>
                                </button>
                              </>
                            )}

                            {pass.status === 'APPROVED' && (
                              <button
                                onClick={() => handlePrintSlip(pass)}
                                title="Print Pass Slip"
                                style={{
                                  display: 'inline-flex', alignItems: 'center', gap: 4,
                                  padding: '0.45rem 0.8rem', borderRadius: 8,
                                  background: D.surfaceHi, color: D.text, border: `1px solid ${D.border}`,
                                  fontSize: '0.8rem', fontWeight: 600, cursor: 'pointer'
                                }}
                              >
                                <Printer size={14} />
                                <span>Slip</span>
                              </button>
                            )}

                            {user?.role === 'ADMIN' && (
                              <button
                                onClick={() => handleDelete(pass.id)}
                                title="Delete Record"
                                style={{
                                  padding: '0.45rem', borderRadius: 8,
                                  background: 'transparent', color: D.textSub, border: 'none',
                                  cursor: 'pointer'
                                }}
                              >
                                <Trash2 size={15} />
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Rejection Reason Modal */}
      {rejectModalOpen && selectedPass && (
        <div style={{
          position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
          background: 'rgba(0, 0, 0, 0.7)', backdropFilter: 'blur(8px)',
          display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: 20
        }}>
          <div style={{
            background: D.surface, border: `1px solid ${D.border}`,
            borderRadius: 18, width: '100%', maxWidth: 480, padding: 24, boxShadow: '0 25px 50px -12px rgba(0,0,0,0.5)'
          }}>
            <h3 style={{ fontSize: '1.2rem', fontWeight: 800, margin: '0 0 8px', color: D.text }}>
              Reject Vehicle Pass Application
            </h3>
            <p style={{ fontSize: '0.88rem', color: D.textSub, margin: '0 0 16px' }}>
              Decline vehicle pass request for <strong>{selectedPass.vehicleRegNumber}</strong> ({selectedPass.applicantName}).
            </p>

            <label style={{ display: 'block', fontSize: '0.78rem', fontWeight: 700, color: D.textSub, textTransform: 'uppercase', marginBottom: 6 }}>
              Reason for Rejection
            </label>
            <textarea
              rows={3}
              placeholder="e.g. Security clearance denied, unauthorized entry period, invalid vehicle details..."
              value={rejectionReason}
              onChange={e => setRejectionReason(e.target.value)}
              style={{
                width: '100%', padding: '0.75rem', borderRadius: 10,
                border: `1px solid ${D.border}`, background: D.surfaceHi,
                color: D.text, fontSize: '0.88rem', fontFamily: 'inherit',
                outline: 'none', resize: 'vertical', boxSizing: 'border-box', marginBottom: 20
              }}
            />

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
              <button
                type="button"
                onClick={() => setRejectModalOpen(false)}
                style={{
                  padding: '0.65rem 1.2rem', borderRadius: 10,
                  border: `1px solid ${D.border}`, background: 'transparent',
                  color: D.text, fontWeight: 600, fontSize: '0.88rem', cursor: 'pointer'
                }}
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleConfirmReject}
                disabled={processingId === selectedPass.id}
                style={{
                  padding: '0.65rem 1.4rem', borderRadius: 10,
                  border: 'none', background: '#ef4444',
                  color: '#fff', fontWeight: 700, fontSize: '0.88rem', cursor: 'pointer'
                }}
              >
                {processingId === selectedPass.id ? 'Rejecting...' : 'Confirm Rejection'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default VehiclePassAdminPage
