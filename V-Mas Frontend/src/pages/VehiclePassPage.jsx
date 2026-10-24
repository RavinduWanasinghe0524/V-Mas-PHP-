import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTheme } from '../context/ThemeContext'
import logo from '../assets/V-MAS Logo.svg'
import { vehiclePassAPI } from '../services/api'
import {
  Car, User, Phone, Mail, IdCard, Calendar, MapPin,
  FileText, CheckCircle, Clock, XCircle, Search, Copy,
  Printer, ArrowLeft, Sun, Moon, ShieldCheck, AlertCircle,
  Truck, Check, Ticket, RefreshCw
} from 'lucide-react'
import './VehiclePassPage.css'

const VEHICLE_TYPES = [
  { value: 'CAR', label: 'Car / Sedan / Hatchback' },
  { value: 'VAN', label: 'Van / Minibus' },
  { value: 'MOTORCYCLE', label: 'Motorcycle / Scooter' },
  { value: 'SUV', label: 'SUV / 4WD' },
  { value: 'LORRY', label: 'Lorry / Truck' },
  { value: 'BUS', label: 'Heavy Bus' },
  { value: 'OTHER', label: 'Other Commercial Vehicle' },
]

const PASS_TYPES = [
  { value: 'VISITOR', label: 'Visitor Pass (Single Day)' },
  { value: 'TEMPORARY', label: 'Temporary Pass (Multi-Day)' },
  { value: 'CONTRACTOR', label: 'Contractor / Vendor Pass' },
  { value: 'STAFF', label: 'Staff / Official Pass' },
]

const VehiclePassPage = () => {
  const navigate = useNavigate()
  const { theme, setTheme } = useTheme()
  const isLight = theme === 'light'

  // Tab State: 'apply' or 'check'
  const [activeTab, setActiveTab] = useState('apply')

  // Application Form State
  const initialForm = {
    applicantName: '',
    phoneNumber: '',
    email: '',
    nicNumber: '',
    vehicleRegNumber: '',
    vehicleType: 'CAR',
    vehicleModel: '',
    passType: 'VISITOR',
    startDate: new Date().toISOString().split('T')[0],
    endDate: '',
    destination: '',
    purpose: '',
  }

  const [formData, setFormData] = useState(initialForm)
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState('')
  const [submittedPass, setSubmittedPass] = useState(null)
  const [copied, setCopied] = useState(false)

  // Status Search State
  const [searchQuery, setSearchQuery] = useState('')
  const [searching, setSearching] = useState(false)
  const [searchResult, setSearchResult] = useState(null)
  const [searchError, setSearchError] = useState('')

  // Handle Form Change
  const handleChange = (e) => {
    const { name, value } = e.target
    setFormData(prev => ({ ...prev, [name]: value }))
  }

  // Handle Application Submit
  const handleSubmit = async (e) => {
    e.preventDefault()
    setSubmitError('')

    if (!formData.applicantName.trim()) {
      setSubmitError('Please enter your full name')
      return
    }
    if (!formData.phoneNumber.trim()) {
      setSubmitError('Please enter your contact phone number')
      return
    }
    if (!formData.vehicleRegNumber.trim()) {
      setSubmitError('Please enter your vehicle registration plate number')
      return
    }
    if (!formData.purpose.trim()) {
      setSubmitError('Please state the purpose of your visit')
      return
    }

    setSubmitting(true)
    try {
      const res = await vehiclePassAPI.apply(formData)
      if (res.data?.success && res.data?.data) {
        setSubmittedPass(res.data.data)
      } else {
        setSubmitError(res.data?.message || 'Failed to submit application')
      }
    } catch (err) {
      setSubmitError(err.response?.data?.message || 'Unable to submit vehicle pass application. Please try again.')
    } finally {
      setSubmitting(false)
    }
  }

  // Handle Status Check Search
  const handleCheckStatus = async (e) => {
    e.preventDefault()
    setSearchError('')
    setSearchResult(null)

    if (!searchQuery.trim()) {
      setSearchError('Please enter a Pass Code (e.g. VP-2026-XXXX) or Phone Number')
      return
    }

    setSearching(true)
    try {
      const res = await vehiclePassAPI.checkStatus(searchQuery.trim())
      if (res.data?.success && res.data?.data) {
        setSearchResult(res.data.data)
      } else {
        setSearchError(res.data?.message || 'No record found')
      }
    } catch (err) {
      setSearchError(err.response?.data?.message || 'No application found matching this Pass Code or Phone Number.')
    } finally {
      setSearching(false)
    }
  }

  // Copy Pass Code helper
  const handleCopy = (code) => {
    if (!code) return
    navigator.clipboard.writeText(code)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  // Print Pass Slip
  const handlePrint = () => {
    window.print()
  }

  return (
    <div className={`vp-page ${isLight ? 'theme-light' : ''}`}>
      <div className="vp-bg-glow" />

      {/* ── Top Navbar ── */}
      <nav className="vp-navbar">
        <div className="vp-brand" onClick={() => navigate('/login')}>
          <img src={logo} alt="V-MAS Logo" className="vp-logo" />
          <div className="vp-brand-text">
            <span className="vp-brand-name">V-MAS</span>
            <span className="vp-brand-sub">Vehicle Pass Portal</span>
          </div>
        </div>

        <div className="vp-nav-actions">
          <button
            type="button"
            className="vp-theme-toggle"
            onClick={() => setTheme(isLight ? 'blue' : 'light')}
            title={isLight ? 'Switch to Night Mode' : 'Switch to Day Mode'}
          >
            {isLight ? <Moon size={16} /> : <Sun size={16} />}
          </button>

          <button
            type="button"
            className="vp-nav-btn"
            onClick={() => navigate('/login')}
          >
            <ArrowLeft size={15} />
            <span>Back to Login</span>
          </button>
        </div>
      </nav>

      {/* ── Main Content Container ── */}
      <main className="vp-main">
        {/* Header Title */}
        <header className="vp-header">
          <div className="vp-badge-pill">
            <ShieldCheck size={14} />
            <span>Public Access & Gate Entry</span>
          </div>
          <h1 className="vp-title">Vehicle Pass Application</h1>
          <p className="vp-subtitle">
            Visitors, contractors, and staff can request authorized vehicle entry permits. No system account required.
          </p>
        </header>

        {/* Tab Switcher */}
        <div className="vp-tabs">
          <button
            type="button"
            className={`vp-tab-btn ${activeTab === 'apply' ? 'active' : ''}`}
            onClick={() => { setActiveTab('apply'); setSubmittedPass(null); }}
          >
            <Ticket size={16} />
            <span>Apply for Pass</span>
          </button>

          <button
            type="button"
            className={`vp-tab-btn ${activeTab === 'check' ? 'active' : ''}`}
            onClick={() => setActiveTab('check')}
          >
            <Search size={16} />
            <span>Check Pass Status</span>
          </button>
        </div>

        {/* ── TAB 1: APPLY FOR PASS ── */}
        {activeTab === 'apply' && (
          <>
            {submittedPass ? (
              /* Success Confirmation Card */
              <div className="vp-result-card">
                <div style={{ display: 'inline-flex', padding: 14, borderRadius: '50%', background: 'rgba(16, 185, 129, 0.15)', color: '#34d399', marginBottom: 12 }}>
                  <CheckCircle size={38} />
                </div>
                <h2 style={{ fontSize: '1.6rem', fontWeight: 800, margin: '0 0 6px' }}>Application Submitted!</h2>
                <p style={{ color: '#94a3b8', fontSize: '0.9rem', maxWidth: 480, margin: '0 auto 1.5rem' }}>
                  Your vehicle pass request has been registered and is pending approval by security administration.
                </p>

                <div className="vp-code-box">
                  <span className="vp-code-text">{submittedPass.passCode}</span>
                  <button
                    type="button"
                    className="vp-copy-btn"
                    onClick={() => handleCopy(submittedPass.passCode)}
                  >
                    {copied ? <Check size={14} color="#34d399" /> : <Copy size={14} />}
                    <span>{copied ? 'Copied!' : 'Copy Code'}</span>
                  </button>
                </div>

                <div style={{ margin: '1rem 0' }}>
                  <span className={`vp-badge-status ${submittedPass.status || 'PENDING'}`}>
                    <Clock size={12} />
                    <span>STATUS: {submittedPass.status || 'PENDING APPROVAL'}</span>
                  </span>
                </div>

                {/* Summary Grid */}
                <div className="vp-summary-grid">
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Applicant Name</span>
                    <span className="vp-summary-val">{submittedPass.applicantName}</span>
                  </div>
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Contact Phone</span>
                    <span className="vp-summary-val">{submittedPass.phoneNumber}</span>
                  </div>
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Vehicle Registration</span>
                    <span className="vp-summary-val" style={{ letterSpacing: '0.05em' }}>{submittedPass.vehicleRegNumber}</span>
                  </div>
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Vehicle Type</span>
                    <span className="vp-summary-val">{submittedPass.vehicleType} {submittedPass.vehicleModel ? `(${submittedPass.vehicleModel})` : ''}</span>
                  </div>
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Pass Type</span>
                    <span className="vp-summary-val">{submittedPass.passType}</span>
                  </div>
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Visit Date</span>
                    <span className="vp-summary-val">
                      {submittedPass.startDate} {submittedPass.endDate ? `to ${submittedPass.endDate}` : ''}
                    </span>
                  </div>
                  {submittedPass.destination && (
                    <div className="vp-summary-item" style={{ gridColumn: 'span 2' }}>
                      <span className="vp-summary-label">Destination / Department</span>
                      <span className="vp-summary-val">{submittedPass.destination}</span>
                    </div>
                  )}
                  <div className="vp-summary-item" style={{ gridColumn: 'span 2' }}>
                    <span className="vp-summary-label">Purpose of Visit</span>
                    <span className="vp-summary-val">{submittedPass.purpose}</span>
                  </div>
                </div>

                <div className="vp-action-row">
                  <button type="button" className="vp-submit-btn" style={{ width: 'auto' }} onClick={handlePrint}>
                    <Printer size={16} />
                    <span>Print Pass Slip</span>
                  </button>

                  <button
                    type="button"
                    className="vp-btn-secondary"
                    onClick={() => { setSubmittedPass(null); setFormData(initialForm); }}
                  >
                    <span>Apply for Another Pass</span>
                  </button>

                  <button
                    type="button"
                    className="vp-btn-secondary"
                    onClick={() => navigate('/login')}
                  >
                    <span>Return to Login</span>
                  </button>
                </div>
              </div>
            ) : (
              /* Application Form Card */
              <div className="vp-card">
                {submitError && (
                  <div style={{
                    display: 'flex', alignItems: 'center', gap: 10,
                    padding: '0.9rem 1.2rem', borderRadius: 12,
                    background: 'rgba(239, 68, 68, 0.12)', border: '1px solid rgba(239, 68, 68, 0.3)',
                    color: '#f87171', fontSize: '0.88rem', marginBottom: '1.5rem'
                  }}>
                    <AlertCircle size={18} />
                    <span>{submitError}</span>
                  </div>
                )}

                <form onSubmit={handleSubmit} noValidate>
                  {/* SECTION 1: APPLICANT DETAILS */}
                  <div className="vp-section">
                    <div className="vp-section-header">
                      <div className="vp-section-icon"><User size={18} /></div>
                      <h3 className="vp-section-title">1. Applicant Information</h3>
                    </div>

                    <div className="vp-grid-2">
                      <div className="vp-form-group">
                        <label className="vp-label">
                          Full Name <span className="vp-req">*</span>
                        </label>
                        <div className="vp-input-wrap">
                          <span className="vp-input-ico"><User size={15} /></span>
                          <input
                            type="text"
                            name="applicantName"
                            className="vp-input"
                            placeholder="e.g. John Doe / Amal Perera"
                            value={formData.applicantName}
                            onChange={handleChange}
                            required
                          />
                        </div>
                      </div>

                      <div className="vp-form-group">
                        <label className="vp-label">
                          Phone Number <span className="vp-req">*</span>
                        </label>
                        <div className="vp-input-wrap">
                          <span className="vp-input-ico"><Phone size={15} /></span>
                          <input
                            type="tel"
                            name="phoneNumber"
                            className="vp-input"
                            placeholder="e.g. 0771234567"
                            value={formData.phoneNumber}
                            onChange={handleChange}
                            required
                          />
                        </div>
                      </div>

                      <div className="vp-form-group">
                        <label className="vp-label">Email Address (Optional)</label>
                        <div className="vp-input-wrap">
                          <span className="vp-input-ico"><Mail size={15} /></span>
                          <input
                            type="email"
                            name="email"
                            className="vp-input"
                            placeholder="email@example.com"
                            value={formData.email}
                            onChange={handleChange}
                          />
                        </div>
                      </div>

                      <div className="vp-form-group">
                        <label className="vp-label">NIC / Passport / ID (Optional)</label>
                        <div className="vp-input-wrap">
                          <span className="vp-input-ico"><IdCard size={15} /></span>
                          <input
                            type="text"
                            name="nicNumber"
                            className="vp-input"
                            placeholder="e.g. 199012345678"
                            value={formData.nicNumber}
                            onChange={handleChange}
                          />
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* SECTION 2: VEHICLE DETAILS */}
                  <div className="vp-section">
                    <div className="vp-section-header">
                      <div className="vp-section-icon"><Car size={18} /></div>
                      <h3 className="vp-section-title">2. Vehicle Information</h3>
                    </div>

                    <div className="vp-grid-3">
                      <div className="vp-form-group">
                        <label className="vp-label">
                          Registration Number (Plate) <span className="vp-req">*</span>
                        </label>
                        <div className="vp-input-wrap">
                          <span className="vp-input-ico"><Truck size={15} /></span>
                          <input
                            type="text"
                            name="vehicleRegNumber"
                            className="vp-input"
                            placeholder="e.g. WP CAB-1234"
                            value={formData.vehicleRegNumber}
                            onChange={(e) => setFormData(prev => ({ ...prev, vehicleRegNumber: e.target.value.toUpperCase() }))}
                            required
                            style={{ textTransform: 'uppercase', letterSpacing: '0.05em', fontWeight: 600 }}
                          />
                        </div>
                      </div>

                      <div className="vp-form-group">
                        <label className="vp-label">Vehicle Type</label>
                        <div className="vp-input-wrap">
                          <select
                            name="vehicleType"
                            className="vp-input vp-input--no-icon vp-select"
                            value={formData.vehicleType}
                            onChange={handleChange}
                          >
                            {VEHICLE_TYPES.map(vt => (
                              <option key={vt.value} value={vt.value}>{vt.label}</option>
                            ))}
                          </select>
                        </div>
                      </div>

                      <div className="vp-form-group">
                        <label className="vp-label">Make & Model (Optional)</label>
                        <div className="vp-input-wrap">
                          <span className="vp-input-ico"><Car size={15} /></span>
                          <input
                            type="text"
                            name="vehicleModel"
                            className="vp-input"
                            placeholder="e.g. Toyota Prius / Isuzu"
                            value={formData.vehicleModel}
                            onChange={handleChange}
                          />
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* SECTION 3: PASS & VISIT DETAILS */}
                  <div className="vp-section">
                    <div className="vp-section-header">
                      <div className="vp-section-icon"><Calendar size={18} /></div>
                      <h3 className="vp-section-title">3. Pass Details & Purpose</h3>
                    </div>

                    <div className="vp-grid-3" style={{ marginBottom: '1.2rem' }}>
                      <div className="vp-form-group">
                        <label className="vp-label">Pass Category</label>
                        <div className="vp-input-wrap">
                          <select
                            name="passType"
                            className="vp-input vp-input--no-icon vp-select"
                            value={formData.passType}
                            onChange={handleChange}
                          >
                            {PASS_TYPES.map(pt => (
                              <option key={pt.value} value={pt.value}>{pt.label}</option>
                            ))}
                          </select>
                        </div>
                      </div>

                      <div className="vp-form-group">
                        <label className="vp-label">
                          Start / Visit Date <span className="vp-req">*</span>
                        </label>
                        <div className="vp-input-wrap">
                          <span className="vp-input-ico"><Calendar size={15} /></span>
                          <input
                            type="date"
                            name="startDate"
                            className="vp-input"
                            value={formData.startDate}
                            onChange={handleChange}
                            required
                          />
                        </div>
                      </div>

                      <div className="vp-form-group">
                        <label className="vp-label">End Date (If Multi-Day)</label>
                        <div className="vp-input-wrap">
                          <span className="vp-input-ico"><Calendar size={15} /></span>
                          <input
                            type="date"
                            name="endDate"
                            className="vp-input"
                            value={formData.endDate}
                            onChange={handleChange}
                            min={formData.startDate}
                          />
                        </div>
                      </div>
                    </div>

                    <div className="vp-form-group" style={{ marginBottom: '1.2rem' }}>
                      <label className="vp-label">Destination / Department to Visit</label>
                      <div className="vp-input-wrap">
                        <span className="vp-input-ico"><MapPin size={15} /></span>
                        <input
                          type="text"
                          name="destination"
                          className="vp-input"
                          placeholder="e.g. Main Administration Building, Warehouse B, Engineering Department"
                          value={formData.destination}
                          onChange={handleChange}
                        />
                      </div>
                    </div>

                    <div className="vp-form-group">
                      <label className="vp-label">
                        Purpose of Visit <span className="vp-req">*</span>
                      </label>
                      <div className="vp-input-wrap">
                        <textarea
                          name="purpose"
                          className="vp-input vp-input--no-icon vp-textarea"
                          placeholder="Provide details regarding the reason for entry (e.g. Official Meeting with Management, Equipment Delivery, Vendor Maintenance, Guest Visit)"
                          value={formData.purpose}
                          onChange={handleChange}
                          required
                        />
                      </div>
                    </div>
                  </div>

                  {/* Submission Row */}
                  <button
                    type="submit"
                    className="vp-submit-btn"
                    disabled={submitting}
                  >
                    {submitting ? (
                      <>
                        <RefreshCw size={18} className="animate-spin" />
                        <span>Submitting Application...</span>
                      </>
                    ) : (
                      <>
                        <Ticket size={18} />
                        <span>Submit Vehicle Pass Application</span>
                      </>
                    )}
                  </button>
                </form>
              </div>
            )}
          </>
        )}

        {/* ── TAB 2: CHECK PASS STATUS ── */}
        {activeTab === 'check' && (
          <div className="vp-card">
            <h3 style={{ fontSize: '1.2rem', fontWeight: 700, margin: '0 0 0.5rem' }}>
              Track Existing Pass Application
            </h3>
            <p style={{ color: '#94a3b8', fontSize: '0.88rem', margin: '0 0 1.5rem' }}>
              Enter your Pass Reference Code (e.g. <code>VP-2026-X8Y9</code>) or the applicant phone number to view clearance status.
            </p>

            <form onSubmit={handleCheckStatus} style={{ display: 'flex', gap: 10, marginBottom: '2rem' }}>
              <div className="vp-input-wrap" style={{ flex: 1 }}>
                <span className="vp-input-ico"><Search size={16} /></span>
                <input
                  type="text"
                  className="vp-input"
                  placeholder="Enter Pass Code (VP-...) or Phone Number"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  required
                />
              </div>

              <button
                type="submit"
                className="vp-submit-btn"
                style={{ width: 'auto', padding: '0.75rem 1.6rem' }}
                disabled={searching}
              >
                {searching ? <RefreshCw size={16} className="animate-spin" /> : <Search size={16} />}
                <span>Check Status</span>
              </button>
            </form>

            {searchError && (
              <div style={{
                display: 'flex', alignItems: 'center', gap: 10,
                padding: '1rem 1.2rem', borderRadius: 12,
                background: 'rgba(239, 68, 68, 0.12)', border: '1px solid rgba(239, 68, 68, 0.3)',
                color: '#f87171', fontSize: '0.88rem'
              }}>
                <XCircle size={18} />
                <span>{searchError}</span>
              </div>
            )}

            {searchResult && (
              <div className="vp-result-card" style={{ padding: '1.8rem', marginTop: '1rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 12, marginBottom: '1.2rem' }}>
                  <div style={{ textAlign: 'left' }}>
                    <span style={{ fontSize: '0.75rem', color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.06em' }}>Pass Reference Code</span>
                    <div style={{ fontSize: '1.4rem', fontWeight: 800, fontFamily: 'monospace', color: '#60a5fa' }}>{searchResult.passCode}</div>
                  </div>

                  <span className={`vp-badge-status ${searchResult.status || 'PENDING'}`}>
                    {searchResult.status === 'APPROVED' && <CheckCircle size={14} />}
                    {searchResult.status === 'PENDING' && <Clock size={14} />}
                    {searchResult.status === 'REJECTED' && <XCircle size={14} />}
                    <span>{searchResult.status}</span>
                  </span>
                </div>

                {searchResult.status === 'REJECTED' && searchResult.rejectionReason && (
                  <div style={{
                    padding: '0.8rem 1.2rem', borderRadius: 10,
                    background: 'rgba(239, 68, 68, 0.1)', border: '1px solid rgba(239, 68, 68, 0.25)',
                    color: '#fca5a5', fontSize: '0.85rem', textAlign: 'left', marginBottom: '1rem'
                  }}>
                    <strong>Reason for rejection:</strong> {searchResult.rejectionReason}
                  </div>
                )}

                {searchResult.status === 'APPROVED' && (
                  <div style={{
                    padding: '0.8rem 1.2rem', borderRadius: 10,
                    background: 'rgba(16, 185, 129, 0.1)', border: '1px solid rgba(16, 185, 129, 0.25)',
                    color: '#6ee7b7', fontSize: '0.85rem', textAlign: 'left', marginBottom: '1rem'
                  }}>
                    <strong>Gate Entry Approved:</strong> Authorized by {searchResult.approvedBy || 'Security Controller'}.
                  </div>
                )}

                <div className="vp-summary-grid">
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Applicant</span>
                    <span className="vp-summary-val">{searchResult.applicantName}</span>
                  </div>
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Phone</span>
                    <span className="vp-summary-val">{searchResult.phoneNumber}</span>
                  </div>
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Vehicle Registration</span>
                    <span className="vp-summary-val">{searchResult.vehicleRegNumber}</span>
                  </div>
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Vehicle Type</span>
                    <span className="vp-summary-val">{searchResult.vehicleType}</span>
                  </div>
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Valid Period</span>
                    <span className="vp-summary-val">{searchResult.startDate} {searchResult.endDate ? `to ${searchResult.endDate}` : ''}</span>
                  </div>
                  <div className="vp-summary-item">
                    <span className="vp-summary-label">Destination</span>
                    <span className="vp-summary-val">{searchResult.destination || 'Campus / Site'}</span>
                  </div>
                  <div className="vp-summary-item" style={{ gridColumn: 'span 2' }}>
                    <span className="vp-summary-label">Purpose</span>
                    <span className="vp-summary-val">{searchResult.purpose}</span>
                  </div>
                </div>

                <div className="vp-action-row" style={{ marginTop: '1.2rem' }}>
                  <button type="button" className="vp-submit-btn" style={{ width: 'auto' }} onClick={handlePrint}>
                    <Printer size={16} />
                    <span>Print Pass Slip</span>
                  </button>
                  <button type="button" className="vp-btn-secondary" onClick={() => handleCopy(searchResult.passCode)}>
                    {copied ? <Check size={14} color="#34d399" /> : <Copy size={14} />}
                    <span>{copied ? 'Copied' : 'Copy Code'}</span>
                  </button>
                </div>
              </div>
            )}
          </div>
        )}
      </main>
    </div>
  )
}

export default VehiclePassPage
