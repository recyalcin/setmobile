import { PrismaClient } from '@prisma/client';
import bcrypt from 'bcryptjs';

const prisma = new PrismaClient();

async function main() {
  console.log('Seeding meaningful enterprise data...');

  // Clear existing data
  await prisma.vehicleAssignment.deleteMany();
  await prisma.vehicleLocation.deleteMany();
  await prisma.workingHours.deleteMany();
  await prisma.driverActivity.deleteMany();
  await prisma.driver.deleteMany();
  await prisma.employee.deleteMany();
  await prisma.user.deleteMany({ where: { username: { not: 'admin@setmobil.com' } } });
  await prisma.person.deleteMany({ where: { id: { not: 1 } } });
  await prisma.vehicle.deleteMany();
  await prisma.transaction.deleteMany();

  // 1. Metadata: Makes, Models, Colors
  const makes = await Promise.all([
    prisma.make.upsert({ where: { id: 1 }, update: {}, create: { id: 1, name: 'Mercedes-Benz' } }),
    prisma.make.upsert({ where: { id: 2 }, update: {}, create: { id: 2, name: 'Volkswagen' } }),
    prisma.make.upsert({ where: { id: 3 }, update: {}, create: { id: 3, name: 'Ford' } }),
    prisma.make.upsert({ where: { id: 4 }, update: {}, create: { id: 4, name: 'Renault' } }),
  ]);

  const models = await Promise.all([
    prisma.model.upsert({ where: { id: 1 }, update: {}, create: { id: 1, makeid: 1, name: 'Vito' } }),
    prisma.model.upsert({ where: { id: 2 }, update: {}, create: { id: 2, makeid: 1, name: 'Sprinter' } }),
    prisma.model.upsert({ where: { id: 3 }, update: {}, create: { id: 3, makeid: 2, name: 'Transporter' } }),
    prisma.model.upsert({ where: { id: 4 }, update: {}, create: { id: 4, makeid: 3, name: 'Transit' } }),
  ]);

  const colors = await Promise.all([
    prisma.color.upsert({ where: { id: 1 }, update: {}, create: { id: 1, name: 'Siyah' } }),
    prisma.color.upsert({ where: { id: 2 }, update: {}, create: { id: 2, name: 'Beyaz' } }),
    prisma.color.upsert({ where: { id: 3 }, update: {}, create: { id: 3, name: 'Gümüş' } }),
  ]);

  // 2. Vehicles
  const vehicles = await Promise.all([
    prisma.vehicle.upsert({ where: { id: 1 }, update: {}, create: { id: 1, makeid: 1, modelid: 1, colorid: 1, licenseplate: '34 ABC 123', note: 'VIP Transfer Aracı' } }),
    prisma.vehicle.upsert({ where: { id: 2 }, update: {}, create: { id: 2, makeid: 2, modelid: 3, colorid: 2, licenseplate: '34 XYZ 789', note: 'Personel Servisi' } }),
    prisma.vehicle.upsert({ where: { id: 3 }, update: {}, create: { id: 3, makeid: 1, modelid: 2, colorid: 1, licenseplate: '34 DEF 456', note: 'Lojistik Aracı' } }),
  ]);

  // 3. Companies & Banks
  const companies = await Promise.all([
    prisma.company.upsert({ where: { id: 1 }, update: {}, create: { id: 1, name: 'Set Mobil Lojistik A.Ş.', city: 'İstanbul', email: 'info@setmobil.com' } }),
    prisma.company.upsert({ where: { id: 2 }, update: {}, create: { id: 2, name: 'Global Petrol Ofisi', city: 'Ankara', note: 'Akaryakıt Tedarikçisi' } }),
  ]);

  const banks = await Promise.all([
    prisma.bank.upsert({ where: { id: 1 }, update: {}, create: { id: 1, name: 'Garanti BBVA' } }),
    prisma.bank.upsert({ where: { id: 2 }, update: {}, create: { id: 2, name: 'İş Bankası' } }),
  ]);

  // 4. Personnel (Drivers & Employees)
  const password = await bcrypt.hash('driver123', 10);

  const personnelData = [
    { first: 'Ahmet', last: 'Yılmaz', email: 'ahmet@setmobil.com', role: 2 },
    { first: 'Mehmet', last: 'Kaya', email: 'mehmet@setmobil.com', role: 2 },
    { first: 'Ayşe', last: 'Demir', email: 'ayse@setmobil.com', role: 2 },
  ];

  for (const p of personnelData) {
    const person = await prisma.person.create({
      data: {
        firstname: p.first,
        lastname: p.last,
        email: p.email,
        persontypeid: p.role,
        city: 'İstanbul',
        country: 'Türkiye'
      }
    });

    await prisma.user.create({
      data: {
        username: p.email,
        password: password,
        personid: person.id,
        active: true
      }
    });

    const driver = await prisma.driver.create({
      data: { personid: person.id }
    });

    const employee = await prisma.employee.create({
      data: { 
        personid: person.id,
        employeenumber: `EMP-${Math.floor(Math.random() * 10000)}`,
        hiredate: new Date('2023-01-01')
      }
    });

    // 5. Simulated Working Hours (History)
    const isFirstDriver = personnelData.indexOf(p) === 0;
    const isSecondDriver = personnelData.indexOf(p) === 1;
    const vehicleId = (personnelData.indexOf(p) % 3) + 1;

    for (let i = 1; i <= 5; i++) {
      const date = new Date();
      date.setDate(date.getDate() - i);
      
      await prisma.workingHours.create({
        data: {
          employeeid: employee.id,
          date: date,
          workstartat: new Date(date.setHours(8, 0, 0)),
          workendat: new Date(date.setHours(18, 0, 0)),
          hours0004: 100000 + (i * 100), // Start KM
          hours2006: 100000 + (i * 100) + 150, // End KM
          hourstotal: 10,
          note: 'Günlük rutin vardiya'
        }
      });
    }

    // Active session for the first driver
    if (isFirstDriver) {
      await prisma.workingHours.create({
        data: {
          employeeid: employee.id,
          vehicleid: vehicleId,
          date: new Date(),
          workstartat: new Date(new Date().setHours(new Date().getHours() - 2)),
          hours0004: 105000,
          note: 'Aktif vardiya'
        }
      });
    }

    // Session ended today for the second driver
    if (isSecondDriver) {
      await prisma.workingHours.create({
        data: {
          employeeid: employee.id,
          vehicleid: vehicleId,
          date: new Date(),
          workstartat: new Date(new Date().setHours(new Date().getHours() - 8)),
          workendat: new Date(new Date().setHours(new Date().getHours() - 1)),
          hours0004: 106000,
          hours2006: 106120,
          note: 'Bugün biten vardiya'
        }
      });
    }

    // 6. Simulated Telemetry (VehicleLocation)
    const offset = personnelData.indexOf(p) * 0.05;
    await prisma.vehicleLocation.create({
      data: {
        vehicleid: vehicleId,
        driverid: driver.id,
        lat: 41.0082 + offset,
        lng: 28.9784 + offset,
        speed: isFirstDriver ? 45 : 0,
        heading: 45 * vehicleId,
        datetime: new Date()
      }
    });

    // 7. Driver Activity
    await prisma.driverActivity.create({
      data: {
        driverid: driver.id,
        vehicleid: vehicleId,
        datetime: new Date(),
        note: isFirstDriver ? 'Sürüş devam ediyor' : 'Park halinde'
      }
    });

    // 8. Simulated Assignments
    await prisma.vehicleAssignment.create({
      data: {
        vehicleid: vehicleId,
        driverid: driver.id,
        assignedat: new Date()
      }
    });
  }

  // 9. Alerts (Tickets)
  await prisma.ticket.createMany({
    data: [
      { subject: 'Hız Sınırı Aşımı', description: '34 ABC 123 plakalı araç 120km/s hıza ulaştı.', priorityid: 3, ticketstatusid: 1, createdat: new Date() },
      { subject: 'Düşük Batarya', description: 'Takip cihazı bataryası %10 altına düştü.', priorityid: 2, ticketstatusid: 1, createdat: new Date() },
    ]
  });

  // 10. Transactions
  await prisma.transaction.createMany({
    data: [
      { amount: 1500, description: 'Yakıt Alımı - 34 ABC 123', date: new Date(), transactiontypeid: 1 },
      { amount: 5000, description: 'Sürücü Maaş Ödemesi - Ahmet Yılmaz', date: new Date(), transactiontypeid: 2 },
      { amount: 1200, description: 'Araç Bakım - 34 XYZ 789', date: new Date(), transactiontypeid: 3 },
    ]
  });

  console.log('Seeding completed successfully!');
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
