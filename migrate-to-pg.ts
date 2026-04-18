/**
 * migrate-to-pg.ts
 * SQLite (prisma/dev.db) → PostgreSQL aktarım scripti
 *
 * Kullanım:
 *   1. PostgreSQL'i başlatın (Docker veya yerel kurulum)
 *   2. npx prisma db push       → PG tablolarını oluşturur
 *   3. npx tsx migrate-to-pg.ts → SQLite verisini PG'ye taşır
 */

import Database from 'better-sqlite3';
import { PrismaClient } from '@prisma/client';
import path from 'path';

const SQLITE_PATH = path.join(process.cwd(), 'prisma', 'dev.db');
const prisma = new PrismaClient();

function toDate(val: any): Date | null {
  if (!val) return null;
  const d = new Date(val);
  return isNaN(d.getTime()) ? null : d;
}

function toDecimal(val: any): number | null {
  if (val === null || val === undefined || val === '') return null;
  const n = parseFloat(val);
  return isNaN(n) ? null : n;
}

async function main() {
  console.log('📦 SQLite → PostgreSQL veri taşıma başlıyor...');
  console.log(`   Kaynak: ${SQLITE_PATH}\n`);

  let sqlite: Database.Database;
  try {
    sqlite = new Database(SQLITE_PATH, { readonly: true });
  } catch (e: any) {
    console.error('❌ SQLite açılamadı:', e.message);
    console.error('   dev.db dosyasının prisma/ klasöründe olduğundan emin olun.');
    process.exit(1);
  }

  // ── PERSON ──────────────────────────────────────────────────────────────
  const persons = sqlite.prepare('SELECT * FROM Person').all() as any[];
  console.log(`👥 Person: ${persons.length} kayıt`);
  await prisma.$executeRaw`TRUNCATE TABLE "Person" RESTART IDENTITY CASCADE`;
  for (const r of persons) {
    await prisma.$executeRaw`
      INSERT INTO "Person" (id, firstname, lastname, email, phone, persontypeid, createdat)
      VALUES (${r.id}, ${r.firstname}, ${r.lastname}, ${r.email}, ${r.phone},
              ${r.persontypeid ?? 2}, ${toDate(r.createdat) ?? new Date()})
      ON CONFLICT (id) DO NOTHING
    `;
  }
  // sequence'ı max id'ye ayarla
  await prisma.$executeRaw`SELECT setval('"Person_id_seq"', (SELECT MAX(id) FROM "Person"))`;

  // ── USER ─────────────────────────────────────────────────────────────────
  const users = sqlite.prepare('SELECT * FROM User').all() as any[];
  console.log(`🔐 User: ${users.length} kayıt`);
  await prisma.$executeRaw`TRUNCATE TABLE "User" RESTART IDENTITY CASCADE`;
  for (const r of users) {
    const pwd = r.password?.replace(/^\$2y\$/, '$2b$') ?? '';
    await prisma.$executeRaw`
      INSERT INTO "User" (id, username, password, personid, active, role, remembertoken, createdat)
      VALUES (${r.id}, ${r.username}, ${pwd}, ${r.personid ?? null},
              ${r.active === 1 || r.active === true}, ${r.role ?? 'driver'},
              ${r.remembertoken ?? null}, ${toDate(r.createdat) ?? new Date()})
      ON CONFLICT (id) DO NOTHING
    `;
  }
  await prisma.$executeRaw`SELECT setval('"User_id_seq"', (SELECT MAX(id) FROM "User"))`;

  // ── DRIVER ───────────────────────────────────────────────────────────────
  const drivers = sqlite.prepare('SELECT * FROM Driver').all() as any[];
  console.log(`🚗 Driver: ${drivers.length} kayıt`);
  await prisma.$executeRaw`TRUNCATE TABLE "Driver" RESTART IDENTITY CASCADE`;
  for (const r of drivers) {
    await prisma.$executeRaw`
      INSERT INTO "Driver" (id, personid, drivertypeid, active, createdat, updatedat)
      VALUES (${r.id}, ${r.personid ?? null}, ${r.drivertypeid ?? null},
              ${r.active === 1 || r.active === true},
              ${toDate(r.createdat)}, ${toDate(r.updatedat)})
      ON CONFLICT (id) DO NOTHING
    `;
  }
  await prisma.$executeRaw`SELECT setval('"Driver_id_seq"', (SELECT MAX(id) FROM "Driver"))`;

  // ── EMPLOYEE ─────────────────────────────────────────────────────────────
  const employees = sqlite.prepare('SELECT * FROM Employee').all() as any[];
  console.log(`🧑‍💼 Employee: ${employees.length} kayıt`);
  await prisma.$executeRaw`TRUNCATE TABLE "Employee" RESTART IDENTITY CASCADE`;
  for (const r of employees) {
    await prisma.$executeRaw`
      INSERT INTO "Employee" (id, personid, employeenumber, employeetypeid, hiredate,
        terminationdate, businessemail, businessphone, note, active, createdat, updatedat)
      VALUES (${r.id}, ${r.personid ?? null}, ${r.employeenumber ?? null},
              ${r.employeetypeid ?? null}, ${toDate(r.hiredate)}, ${toDate(r.terminationdate)},
              ${r.businessemail ?? null}, ${r.businessphone ?? null}, ${r.note ?? null},
              ${r.active === 1 || r.active === true},
              ${toDate(r.createdat)}, ${toDate(r.updatedat)})
      ON CONFLICT (id) DO NOTHING
    `;
  }
  await prisma.$executeRaw`SELECT setval('"Employee_id_seq"', (SELECT MAX(id) FROM "Employee"))`;

  // ── VEHICLE ──────────────────────────────────────────────────────────────
  const vehicles = sqlite.prepare('SELECT * FROM Vehicle').all() as any[];
  console.log(`🚙 Vehicle: ${vehicles.length} kayıt`);
  await prisma.$executeRaw`TRUNCATE TABLE "Vehicle" RESTART IDENTITY CASCADE`;
  for (const r of vehicles) {
    await prisma.$executeRaw`
      INSERT INTO "Vehicle" (id, licenseplate, makeid, modelid, colorid, vehicletypeid,
        active, note, createdat, updatedat)
      VALUES (${r.id}, ${r.licenseplate ?? null}, ${r.makeid ?? null}, ${r.modelid ?? null},
              ${r.colorid ?? null}, ${r.vehicletypeid ?? null},
              ${r.active === 1 || r.active === true}, ${r.note ?? null},
              ${toDate(r.createdat)}, ${toDate(r.updatedat)})
      ON CONFLICT (id) DO NOTHING
    `;
  }
  await prisma.$executeRaw`SELECT setval('"Vehicle_id_seq"', (SELECT MAX(id) FROM "Vehicle"))`;

  // ── VEHICLE ASSIGNMENT ───────────────────────────────────────────────────
  const assignments = sqlite.prepare('SELECT * FROM VehicleAssignment').all() as any[];
  console.log(`🔗 VehicleAssignment: ${assignments.length} kayıt`);
  await prisma.$executeRaw`TRUNCATE TABLE "VehicleAssignment" RESTART IDENTITY CASCADE`;
  for (const r of assignments) {
    await prisma.$executeRaw`
      INSERT INTO "VehicleAssignment" (id, driverid, vehicleid, assignedat, unassignedat)
      VALUES (${r.id}, ${r.driverid ?? null}, ${r.vehicleid ?? null},
              ${toDate(r.assignedat) ?? new Date()}, ${toDate(r.unassignedat)})
      ON CONFLICT (id) DO NOTHING
    `;
  }
  await prisma.$executeRaw`SELECT setval('"VehicleAssignment_id_seq"', COALESCE((SELECT MAX(id) FROM "VehicleAssignment"), 1))`;

  // ── WORKING HOURS ────────────────────────────────────────────────────────
  const whs = sqlite.prepare('SELECT * FROM WorkingHours').all() as any[];
  console.log(`⏱️  WorkingHours: ${whs.length} kayıt`);
  await prisma.$executeRaw`TRUNCATE TABLE "WorkingHours" RESTART IDENTITY CASCADE`;
  let whOk = 0, whSkip = 0;
  for (const r of whs) {
    try {
      await prisma.$executeRaw`
        INSERT INTO "WorkingHours" (id, employeeid, vehicleid, date, firsttripat, lasttripat,
          workstartat, workendat, breakduration, hourstotal, startkm, endkm, note, createdat, updatedat)
        VALUES (${r.id}, ${r.employeeid ?? null}, ${r.vehicleid ?? null},
                ${toDate(r.date)}, ${toDate(r.firsttripat)}, ${toDate(r.lasttripat)},
                ${toDate(r.workstartat)}, ${toDate(r.workendat)},
                ${r.breakduration ?? null},
                ${toDecimal(r.hourstotal)}, ${toDecimal(r.startkm)}, ${toDecimal(r.endkm)},
                ${r.note ?? null}, ${toDate(r.createdat)}, ${toDate(r.updatedat)})
        ON CONFLICT (id) DO NOTHING
      `;
      whOk++;
    } catch { whSkip++; }
  }
  console.log(`   ✓ ${whOk} eklendi, ${whSkip} atlandı`);
  await prisma.$executeRaw`SELECT setval('"WorkingHours_id_seq"', COALESCE((SELECT MAX(id) FROM "WorkingHours"), 1))`;

  // ── VEHICLE LOCATION ─────────────────────────────────────────────────────
  const locs = sqlite.prepare('SELECT * FROM VehicleLocation').all() as any[];
  console.log(`📍 VehicleLocation: ${locs.length} kayıt`);
  await prisma.$executeRaw`TRUNCATE TABLE "VehicleLocation" RESTART IDENTITY CASCADE`;
  for (const r of locs) {
    await prisma.$executeRaw`
      INSERT INTO "VehicleLocation" (id, driverid, vehicleid, tripid, lat, lng,
        speed, heading, datetime, note)
      VALUES (${r.id}, ${r.driverid ?? null}, ${r.vehicleid ?? null}, ${r.tripid ?? null},
              ${toDecimal(r.lat) ?? 0}, ${toDecimal(r.lng) ?? 0},
              ${toDecimal(r.speed) ?? 0}, ${r.heading ?? 0},
              ${toDate(r.datetime) ?? new Date()}, ${r.note ?? null})
      ON CONFLICT (id) DO NOTHING
    `;
  }
  await prisma.$executeRaw`SELECT setval('"VehicleLocation_id_seq"', COALESCE((SELECT MAX(id) FROM "VehicleLocation"), 1))`;

  // ── TICKET ───────────────────────────────────────────────────────────────
  const tickets = sqlite.prepare('SELECT * FROM Ticket').all() as any[];
  console.log(`🎫 Ticket: ${tickets.length} kayıt`);
  await prisma.$executeRaw`TRUNCATE TABLE "Ticket" RESTART IDENTITY CASCADE`;
  for (const r of tickets) {
    await prisma.$executeRaw`
      INSERT INTO "Ticket" (id, subject, body, priorityid, statusid, categoryid, typeid,
        driverid, vehicleid, note, createdat, updatedat)
      VALUES (${r.id}, ${r.subject ?? null}, ${r.body ?? null},
              ${r.priorityid ?? 1}, ${r.statusid ?? 1},
              ${r.categoryid ?? null}, ${r.typeid ?? null},
              ${r.driverid ?? null}, ${r.vehicleid ?? null},
              ${r.note ?? null}, ${toDate(r.createdat)}, ${toDate(r.updatedat)})
      ON CONFLICT (id) DO NOTHING
    `;
  }
  await prisma.$executeRaw`SELECT setval('"Ticket_id_seq"', COALESCE((SELECT MAX(id) FROM "Ticket"), 1))`;

  // ── TRIP ─────────────────────────────────────────────────────────────────
  const trips = sqlite.prepare('SELECT * FROM Trip').all() as any[];
  console.log(`🗺️  Trip: ${trips.length} kayıt`);
  await prisma.$executeRaw`TRUNCATE TABLE "Trip" CASCADE`;
  let tripOk = 0;
  for (const r of trips) {
    try {
      await prisma.$executeRaw`
        INSERT INTO "Trip" (id, triptypeid, tripstatusid, tripsourceid, vehicleid, driverid,
          submittedat, transmittedat, respondedat, pickedupat, arrivedat,
          latvehiclelocationatorder, lngvehiclelocationatorder,
          latpickuplocation, lngpickuplocation, latdropofflocation, lngdropofflocation,
          pickupdistance, tripdistance, fare, paymenttypeid, note, createdat, updatedat)
        VALUES (${r.id}, ${r.triptypeid ?? null}, ${r.tripstatusid ?? null},
                ${r.tripsourceid ?? null}, ${r.vehicleid ?? null}, ${r.driverid ?? null},
                ${toDate(r.submittedat)}, ${toDate(r.transmittedat)}, ${toDate(r.respondedat)},
                ${toDate(r.pickedupat)}, ${toDate(r.arrivedat)},
                ${toDecimal(r.latvehiclelocationatorder)}, ${toDecimal(r.lngvehiclelocationatorder)},
                ${toDecimal(r.latpickuplocation)}, ${toDecimal(r.lngpickuplocation)},
                ${toDecimal(r.latdropofflocation)}, ${toDecimal(r.lngdropofflocation)},
                ${toDecimal(r.pickupdistance)}, ${toDecimal(r.tripdistance)},
                ${toDecimal(r.fare)}, ${r.paymenttypeid ?? null}, ${r.note ?? null},
                ${toDate(r.createdat)}, ${toDate(r.updatedat)})
        ON CONFLICT (id) DO NOTHING
      `;
      tripOk++;
      if (tripOk % 200 === 0) process.stdout.write(`   ${tripOk}/${trips.length}...\r`);
    } catch { /* skip orphan */ }
  }
  console.log(`   ✓ ${tripOk}/${trips.length} trip eklendi`);

  // ── PERFORMANCE ──────────────────────────────────────────────────────────
  const perfs = sqlite.prepare('SELECT * FROM Performance').all() as any[];
  console.log(`📊 Performance: ${perfs.length} kayıt`);
  await prisma.$executeRaw`TRUNCATE TABLE "Performance" RESTART IDENTITY CASCADE`;
  for (const r of perfs) {
    await prisma.$executeRaw`
      INSERT INTO "Performance" (id, driverid, week,
        netearningsbolt, tollfeesbolt, ridertipsbolt, collectedcashbolt, earningsperformancebolt,
        finishedridesbolt, onlinetimebolt, totalridedistancebolt, totalacceptanceratebolt,
        netearningsuber, tollfeesuber, ridertipsuber, collectedcashuber, earningsperformanceuber,
        finishedridesuber, onlinetimeuber, totalridedistanceuber, totalacceptancerateuber,
        note, createdat, updatedat)
      VALUES (${r.id}, ${r.driverid ?? null}, ${r.week ?? null},
        ${toDecimal(r.netearningsbolt)}, ${toDecimal(r.tollfeesbolt)},
        ${toDecimal(r.ridertipsbolt)}, ${toDecimal(r.collectedcashbolt)},
        ${toDecimal(r.earningsperformancebolt)}, ${r.finishedridesbolt ?? null},
        ${toDecimal(r.onlinetimebolt)}, ${toDecimal(r.totalridedistancebolt)},
        ${toDecimal(r.totalacceptanceratebolt)},
        ${toDecimal(r.netearningsuber)}, ${toDecimal(r.tollfeesuber)},
        ${toDecimal(r.ridertipsuber)}, ${toDecimal(r.collectedcashuber)},
        ${toDecimal(r.earningsperformanceuber)}, ${r.finishedridesuber ?? null},
        ${toDecimal(r.onlinetimeuber)}, ${toDecimal(r.totalridedistanceuber)},
        ${toDecimal(r.totalacceptancerateuber)},
        ${r.note ?? null}, ${toDate(r.createdat)}, ${toDate(r.updatedat)})
      ON CONFLICT (id) DO NOTHING
    `;
  }
  await prisma.$executeRaw`SELECT setval('"Performance_id_seq"', COALESCE((SELECT MAX(id) FROM "Performance"), 1))`;

  sqlite.close();
  console.log('\n✅ Tüm veriler PostgreSQL\'e başarıyla aktarıldı!');

  // Özet
  const counts = await Promise.all([
    prisma.person.count(),
    prisma.user.count(),
    prisma.driver.count(),
    prisma.employee.count(),
    prisma.vehicle.count(),
    prisma.workingHours.count(),
    prisma.trip.count(),
    prisma.performance.count(),
  ]);
  console.log('\n📈 PostgreSQL kayıt sayıları:');
  const labels = ['Person','User','Driver','Employee','Vehicle','WorkingHours','Trip','Performance'];
  labels.forEach((l, i) => console.log(`   ${l.padEnd(16)}: ${counts[i]}`));
}

main()
  .catch(e => { console.error('❌ Hata:', e); process.exit(1); })
  .finally(() => prisma.$disconnect());
