// Database connection module
import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config();

// Create a connection pool
const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'kemp',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

/**
 * Execute a SQL query with parameters
 * @param {string} sql - SQL query
 * @param {Array} params - Query parameters
 * @returns {Promise} - Query result
 */
export async function query(sql, params) {
  try {
    const [rows] = await pool.execute(sql, params);
    return rows;
  } catch (error) {
    console.error('Database error:', error);
    throw error;
  }
}

/**
 * Get all available activities
 * @returns {Promise} - Query result with available activities
 */
export async function getAvailableActivities() {
  return query('SELECT * FROM dostupne_aktivity');
}

/**
 * Get all available accommodations based on gender
 * @param {string} pohlavie - Gender ('muz' or 'zena')
 * @param {string} typ - Registration type ('ucastnik', 'veduci', or 'host')
 * @returns {Promise} - Query result with available accommodations
 */
export async function getAvailableAccommodation(pohlavie, typ) {
  let conditions = ['u.kapacita - COUNT(ouu.id) > 0'];
  const params = [];

  // For participants, only show gender-specific and mixed accommodations
  if (typ === 'ucastnik') {
    conditions.push('(u.pohlavie = ? OR u.pohlavie = "spolocne")');
    params.push(pohlavie);
  }
  // For leaders, show leader-specific accommodations and their gender-specific ones
  else if (typ === 'veduci') {
    conditions.push('(u.pohlavie = ? OR u.pohlavie = "spolocne" OR u.pohlavie = "veduci")');
    params.push(pohlavie);
  }
  // For guests, show all accommodations
  else {
    conditions.push('(u.pohlavie = ? OR u.pohlavie = "spolocne")');
    params.push(pohlavie);
  }

  const sql = `
    SELECT
      u.id,
      u.nazov,
      u.kapacita,
      u.pohlavie,
      u.kapacita - COUNT(ouu.id) AS available_spots
    FROM
      ubytovanie u
    LEFT JOIN
      os_udaje_ubytovanie ouu ON u.id = ouu.ubytovanie_id
    GROUP BY
      u.id, u.nazov, u.kapacita, u.pohlavie
    HAVING
      ${conditions.join(' AND ')}
  `;

  return query(sql, params);
}

/**
 * Get all youth groups
 * @returns {Promise} - Query result with youth groups
 */
export async function getYouthGroups() {
  return query('SELECT * FROM mladez');
}

/**
 * Get all allergies
 * @returns {Promise} - Query result with allergies
 */
export async function getAllergies() {
  return query('SELECT * FROM alergie WHERE nazov != "iné"');
}

/**
 * Register a participant
 * @param {Object} data - Registration data
 * @returns {Promise} - Query result
 */
export async function registerParticipant(data) {
  // Start a transaction
  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();

    // Insert personal data
    const [personalResult] = await connection.execute(
      `INSERT INTO os_udaje
        (meno, priezvisko, email, vek, pohlavie, mladez_id, vlastny_mladez, prvy_krat, poznamka, gdpr, typ)
       VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        data.meno,
        data.priezvisko,
        data.email,
        data.vek,
        data.pohlavie,
        data.mladez_id || null,
        data.vlastny_mladez || null,
        data.prvy_krat ? 1 : 0,
        data.poznamka || null,
        1, // GDPR consent is required
        data.typ || 'ucastnik'
      ]
    );

    const os_udaje_id = personalResult.insertId;

    // Insert activities
    if (data.aktivity && data.aktivity.length > 0) {
      for (const aktivita_id of data.aktivity) {
        await connection.execute(
          'INSERT INTO os_udaje_aktivity (os_udaje_id, aktivita_id) VALUES (?, ?)',
          [os_udaje_id, aktivita_id]
        );
      }
    }

    // Insert allergies
    if (data.alergie && data.alergie.length > 0) {
      for (const alergia_id of data.alergie) {
        await connection.execute(
          'INSERT INTO os_udaje_alergie (os_udaje_id, alergia_id) VALUES (?, ?)',
          [os_udaje_id, alergia_id]
        );
      }
    }

    // Insert custom allergies
    if (data.vlastne_alergie) {
      await connection.execute(
        'INSERT INTO os_udaje_alergie (os_udaje_id, vlastna_alergia) VALUES (?, ?)',
        [os_udaje_id, data.vlastne_alergie]
      );
    }

    // Insert accommodation
    if (data.ubytovanie_id) {
      await connection.execute(
        'INSERT INTO os_udaje_ubytovanie (os_udaje_id, ubytovanie_id) VALUES (?, ?)',
        [os_udaje_id, data.ubytovanie_id]
      );
    }

    // Commit the transaction
    await connection.commit();
    return { success: true, id: os_udaje_id };
  } catch (error) {
    // Rollback in case of error
    await connection.rollback();
    console.error('Registration error:', error);
    throw error;
  } finally {
    connection.release();
  }
}

export default {
  query,
  getAvailableActivities,
  getAvailableAccommodation,
  getYouthGroups,
  getAllergies,
  registerParticipant
};
