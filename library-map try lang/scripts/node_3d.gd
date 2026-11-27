extends Node3D

@onready var default_camera: Camera3D = $default_camera
@onready var top_down_camera: Camera3D = $top_down_camera
@onready var section_1_cam: Camera3D = $section1_cam
@onready var section_2_cam: Camera3D = $section2_cam
@onready var section_3_cam: Camera3D = $section_3_cam
@onready var section_4_cam: Camera3D = $section_4_cam
@onready var section_5_cam: Camera3D = $section_5_cam
@onready var section_6_cam: Camera3D = $section_6_cam
@onready var section_7_cam: Camera3D = $section_7_cam

@onready var genre: Label = $default_camera/genre
@onready var label: Label = $default_camera/Label
@onready var genre_selected: Sprite2D = $default_camera/genre_selected
@onready var genre_bg: Sprite2D = $default_camera/genre_bg

# SECTION 1 SHELVES
@onready var philo_marker: Sprite3D = $Philosophy/philo_marker
@onready var psych_marker: Sprite3D = $Psychology/psych_marker
@onready var educ_marker: Sprite3D = $Education/educ_marker
@onready var sci_marker: Sprite3D = $Science/sci_marker
@onready var eng_marker: Sprite3D = $English/eng_marker
@onready var law_marker: Sprite3D = $Law/law_marker

@onready var hide: Button = $default_camera/hide
@onready var show: Button = $default_camera/show
@onready var sprite_2d: Sprite2D = $default_camera/Sprite2D


# SECTION 2
@onready var mis_marker: Sprite3D = $Miscellaneous/mis_marker
@onready var sec_2_highlight: Sprite3D = $sec2_highlight

# SECTION 3 - 6 SHELVES
@onready var mis2_marker: Sprite3D = $Miscellaneous2/mis_marker
@onready var fil_marker: Sprite3D = $Filipino/fil_marker
@onready var genre_marker: Sprite3D = $Genre/genre_marker
@onready var math_marker: Sprite3D = $Math/math_marker
@onready var lit_marker: Sprite3D = $Literature/lit_marker
@onready var hum_marker: Sprite3D = $Humanities/hum_marker
@onready var research_marker: Sprite3D = $Research/research_marker
@onready var econ_marker: Sprite3D = $Economics/econ_marker
@onready var acc_marker: Sprite3D = $Accounting/acc_marker
@onready var mgt_marker: Sprite3D = $Management/mgt_marker
@onready var entrep_marker: Sprite3D = $Entrep/entrep_marker
@onready var mint_marker: Sprite3D = $MintGreen/mint_marker
@onready var comp_marker: Sprite3D = $Computer/comp_marker
@onready var elec_marker: Sprite3D = $Electronics/elec_marker
@onready var entrep2_marker: Sprite3D = $Entrep2/entrep_marker
@onready var comp2_marker: Sprite3D = $Computer2/comp_marker
@onready var comp2_marker_b: Sprite3D = $Computer2/comp_marker2
@onready var comp3_marker: Sprite3D = $Computer3/comp_marker
@onready var shs2_marker: Sprite3D = $SHS2/shs_marker
@onready var shs_marker: Sprite3D = $SHS/shs_marker
@onready var econ2_marker: Sprite3D = $Economics2/econ_marker
@onready var nstp_marker: Sprite3D = $NSTP/nstp_marker
@onready var nstp_marker_b: Sprite3D = $NSTP/nstp_marker2
@onready var pe_marker: Sprite3D = $PE/pe_marker
@onready var art_marker: Sprite3D = $"Art Appreciation/art_marker"

@onready var sec_1_highlight: Sprite3D = $sec1_highlight
@onready var sec_3_highlight: Sprite3D = $sec3_highlight
@onready var sec_4_highlight: Sprite3D = $sec4_highlight
@onready var sec_5_highlight: Sprite3D = $sec5_highlight
@onready var sec_5b_highlight: Sprite3D = $sec5b_highlight
@onready var sec_6_highlight: Sprite3D = $sec6_highlight
@onready var path_to_section_1: Path3D = $Path_To_Section_1
@onready var path_to_section_2: Path3D = $Path_To_Section_2
@onready var path_to_section_3: Path3D = $Path_To_Section_3
@onready var path_to_section_4: Path3D = $Path_To_Section_4
@onready var path_to_section_5: Path3D = $Path_To_Section_5
@onready var path_to_section_6: Path3D = $Path_To_Section_6

@onready var line_edit: LineEdit = $default_camera/LineEdit

var currently_clicked := ""
var camera_tween: Tween
var current_cam: Camera3D
var is_camera_moving := false

var clicked_section := 0 

var search_queue = ""

var philosophy_books := [
	"The Republic\nPlato\n380 BC",
	"Meditations\nMarcus Aurelius\n180",
	"Beyond Good and Evil\nFriedrich Nietzsche\n1886"
]

var psychology_books := [
	"Thinking, Fast and Slow\nDaniel Kahneman\n2011",
	"Psychology\nWilliam James\n1892",
	"Man's Search for Meaning\nViktor Frankl\n1946"
]

var education_books := [
	"Pedagogy of the Oppressed\nPaulo Freire\n1968",
	"Democracy and Education\nJohn Dewey\n1916",
	"Educated\nTara Westover\n2018"
]

var science_books := [
	"A Brief History of Time\nStephen Hawking\n1988",
	"The Origin of Species\nCharles Darwin\n1859",
	"Cosmos\nCarl Sagan\n1980"
]

var english_books := [
	"To Kill a Mockingbird\nHarper Lee\n1960",
	"The Great Gatsby\nF. Scott Fitzgerald\n1925",
	"1984\nGeorge Orwell\n1949"
]

var law_books := [
	"The Concept of Law\nH. L. A. Hart\n1961",
	"On Liberty\nJohn Stuart Mill\n1859",
	"The Common Law\nOliver Wendell Holmes Jr.\n1881"
]

var misc_books := [
	"The Art of War\nSun Tzu\n5th century BC",
	"Sapiens\nYuval Noah Harari\n2011",
	"The Book of General Ignorance\nJohn Lloyd and John Mitchinson\n2006"
]

var filipino_books := [
	"Noli Me Tangere\nJose Rizal\n1887",
	"El Filibusterismo\nJose Rizal\n1891",
	"Florante at Laura\nFrancisco Balagtas\n1838"
]

var genre_books := [
	"The Hobbit\nJ.R.R. Tolkien\n1937",
	"Harry Potter and the Sorcerer's Stone\nJ.K. Rowling\n1997",
	"The Hunger Games\nSuzanne Collins\n2008"
]

var math_books := [
	"Elements\nEuclid\n300 BC",
	"How Not to Be Wrong\nJordan Ellenberg\n2014",
	"The Joy of x\nSteven Strogatz\n2012"
]

var literature_books := [
	"Pride and Prejudice\nJane Austen\n1813",
	"Jane Eyre\nCharlotte Bronte\n1847",
	"Wuthering Heights\nEmily Bronte\n1847"
]

var humanities_books := [
	"The Story of Art\nE. H. Gombrich\n1950",
	"The Lessons of History\nWill and Ariel Durant\n1968",
	"The Human Condition\nHannah Arendt\n1958"
]

var research_books := [
	"The Craft of Research\nWayne C. Booth et al.\n1995",
	"Research Design\nJohn W. Creswell\n1992",
	"Doing Your Research Project\nJudith Bell\n1988"
]

var economics_books := [
	"The Wealth of Nations\nAdam Smith\n1776",
	"Capital in the Twenty-First Century\nThomas Piketty\n2013",
	"Freakonomics\nSteven D. Levitt and Stephen J. Dubner\n2005"
]

var accounting_books := [
	"Accounting Principles\nJerry J. Weygandt et al.\n2010",
	"Financial Accounting\nCarl S. Warren et al.\n2008",
	"The Interpretation of Financial Statements\nBenjamin Graham and Spencer B. Meredith\n1937"
]

var management_books := [
	"The Practice of Management\nPeter F. Drucker\n1954",
	"Good to Great\nJim Collins\n2001",
	"The 7 Habits of Highly Effective People\nStephen R. Covey\n1989"
]

var entrep_books := [
	"The Lean Startup\nEric Ries\n2011",
	"Zero to One\nPeter Thiel\n2014",
	"The $100 Startup\nChris Guillebeau\n2012"
]

var mintgreen_books := [
	"The Secret Garden\nFrances Hodgson Burnett\n1911",
	"Anne of Green Gables\nL. M. Montgomery\n1908",
	"Little Women\nLouisa May Alcott\n1868"
]

var computer_books := [
	"Clean Code\nRobert C. Martin\n2008",
	"The Pragmatic Programmer\nAndrew Hunt and David Thomas\n1999",
	"Introduction to Algorithms\nThomas H. Cormen et al.\n1989"
]

var electronics_books := [
	"The Art of Electronics\nPaul Horowitz and Winfield Hill\n1980",
	"Electronic Devices and Circuit Theory\nRobert L. Boylestad\n1976",
	"Make: Electronics\nCharles Platt\n2009"
]

var shs_books := [
	"The Alchemist\nPaulo Coelho\n1988",
	"Atomic Habits\nJames Clear\n2018",
	"The Little Prince\nAntoine de Saint-Exupery\n1943"
]

var nstp_books := [
	"Pedagogy of the Oppressed\nPaulo Freire\n1968",
	"Community Development\nJim Cavaye\n1995",
	"Development as Freedom\nAmartya Sen\n1999"
]

var pe_books := [
	"Physical Education and Sport\nDaryl Siedentop\n1998",
	"Exercise Physiology\nWilliam D. McArdle et al.\n1986",
	"Foundations of Physical Education\nCharles A. Bucher\n1947"
]

var art_books := [
	"The Story of Art\nE. H. Gombrich\n1950",
	"Drawing on the Right Side of the Brain\nBetty Edwards\n1979",
	"Steal Like an Artist\nAustin Kleon\n2012"
]

func _ready():
	current_cam = default_camera
	default_camera.make_current()


func _process(delta):
	if Input.is_action_pressed("left"):
		default_camera.rotation_degrees.y += 1
		top_down_camera.rotation_degrees.y += 1

	if Input.is_action_pressed("right"):
		default_camera.rotation_degrees.y -= 1
		top_down_camera.rotation_degrees.y -= 1
		
	if Input.is_action_just_pressed("top_down"):
		animate_camera(top_down_camera)

	if Input.is_action_just_pressed("bottom_up"):
		animate_camera(default_camera)

	# key 4 = back to overview
	if Input.is_action_just_pressed("4"):
		animate_camera(default_camera)
		sec_1_highlight.visible = false
		sec_2_highlight.visible = false
		hide_extra_highlights()
		hide_extra_paths()
		path_to_section_1.visible = false


func hide_extra_paths():
	path_to_section_2.visible = false
	path_to_section_3.visible = false
	path_to_section_4.visible = false
	path_to_section_5.visible = false
	path_to_section_6.visible = false


func hide_extra_highlights():
	sec_3_highlight.visible = false
	sec_4_highlight.visible = false
	sec_5_highlight.visible = false
	sec_5b_highlight.visible = false
	sec_6_highlight.visible = false


func show_genre():
	if currently_clicked == "PHILOSOPHY":
		genre.text = "PHILOSOPHY"
		label.text = "\n \n".join(philosophy_books)

	if currently_clicked == "PSYCHOLOGY":
		genre.text = "PSYCHOLOGY"
		label.text = "\n \n".join(psychology_books)

	if currently_clicked == "EDUCATION":
		genre.text = "EDUCATION"
		label.text = "\n \n".join(education_books)

	if currently_clicked == "SCIENCE":
		genre.text = "SCIENCE"
		label.text = "\n \n".join(science_books)

	if currently_clicked == "ENGLISH":
		genre.text = "ENGLISH"
		label.text = "\n \n".join(english_books)

	if currently_clicked == "LAW":
		genre.text = "LAW"
		label.text = "\n \n".join(law_books)

	if currently_clicked == "MISC":
		genre.text = "MISC"
		label.text = "\n \n".join(misc_books)

	if currently_clicked == "MISC2":
		genre.text = "MISC"
		label.text = "\n \n".join(misc_books)

	if currently_clicked == "FILIPINO":
		genre.text = "FILIPINO"
		label.text = "\n \n".join(filipino_books)

	if currently_clicked == "GENRE":
		genre.text = "GENRE"
		label.text = "\n \n".join(genre_books)

	if currently_clicked == "MATH":
		genre.text = "MATH"
		label.text = "\n \n".join(math_books)

	if currently_clicked == "LITERATURE":
		genre.text = "LITERATURE"
		label.text = "\n \n".join(literature_books)

	if currently_clicked == "HUMANITIES":
		genre.text = "HUMANITIES"
		label.text = "\n \n".join(humanities_books)

	if currently_clicked == "RESEARCH":
		genre.text = "RESEARCH"
		label.text = "\n \n".join(research_books)

	if currently_clicked == "ECONOMICS":
		genre.text = "ECONOMICS"
		label.text = "\n \n".join(economics_books)

	if currently_clicked == "ACCOUNTING":
		genre.text = "ACCOUNTING"
		label.text = "\n \n".join(accounting_books)

	if currently_clicked == "MANAGEMENT":
		genre.text = "MANAGEMENT"
		label.text = "\n \n".join(management_books)

	if currently_clicked == "ENTREP":
		genre.text = "ENTREP"
		label.text = "\n \n".join(entrep_books)

	if currently_clicked == "MINTGREEN":
		genre.text = "MINTGREEN"
		label.text = "\n \n".join(mintgreen_books)

	if currently_clicked == "COMPUTER":
		genre.text = "COMPUTER"
		label.text = "\n \n".join(computer_books)

	if currently_clicked == "ELECTRONICS":
		genre.text = "ELECTRONICS"
		label.text = "\n \n".join(electronics_books)

	if currently_clicked == "SHS":
		genre.text = "SHS"
		label.text = "\n \n".join(shs_books)

	if currently_clicked == "NSTP":
		genre.text = "NSTP"
		label.text = "\n \n".join(nstp_books)

	if currently_clicked == "PE":
		genre.text = "PE"
		label.text = "\n \n".join(pe_books)

	if currently_clicked == "ART APPRECIATION":
		genre.text = "ART APPRECIATION"
		label.text = "\n \n".join(art_books)

	genre_selected.visible = true
	genre_bg.visible = false


func animate_camera(next_cam: Camera3D):
	# DEBUG: delete this line when everything works
	print("go to: ", next_cam.name, " | current: ", current_cam.name, " | moving: ", is_camera_moving)

	if next_cam == current_cam or is_camera_moving:
		return

	is_camera_moving = true

	var target_pos = next_cam.global_position
	next_cam.global_position = current_cam.global_position
	next_cam.make_current()
	current_cam = next_cam

	camera_tween = create_tween()
	camera_tween.set_trans(Tween.TRANS_CUBIC).set_ease(Tween.EASE_IN_OUT)
	camera_tween.tween_property(next_cam, "global_position", target_pos, 1.0)
	camera_tween.finished.connect(func(): is_camera_moving = false)


# CLICKS
func _on_philosophy_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "PHILOSOPHY"
		clicked_section = 1
		animate_camera(section_1_cam)
		show_genre()

func _on_psychology_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "PSYCHOLOGY"
		clicked_section = 1
		animate_camera(section_1_cam)
		show_genre()

func _on_education_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "EDUCATION"
		clicked_section = 1
		animate_camera(section_1_cam)
		show_genre()

func _on_science_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "SCIENCE"
		clicked_section = 1
		animate_camera(section_1_cam)
		show_genre()

func _on_english_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "ENGLISH"
		clicked_section = 1
		animate_camera(section_1_cam)
		show_genre()

func _on_law_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "LAW"
		clicked_section = 1
		animate_camera(section_1_cam)
		show_genre()

func _on_miscellaneous_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "MISC"
		clicked_section = 2
		animate_camera(section_2_cam)
		show_genre()


func _on_miscellaneous2_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "MISC2"
		clicked_section = 3
		animate_camera(section_3_cam)
		show_genre()

func _on_filipino_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "FILIPINO"
		clicked_section = 3
		animate_camera(section_3_cam)
		show_genre()

func _on_genre_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "GENRE"
		clicked_section = 3
		animate_camera(section_3_cam)
		show_genre()

func _on_math_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "MATH"
		clicked_section = 3
		animate_camera(section_3_cam)
		show_genre()

func _on_literature_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "LITERATURE"
		clicked_section = 3
		animate_camera(section_3_cam)
		show_genre()

func _on_humanities_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "HUMANITIES"
		clicked_section = 4
		animate_camera(section_4_cam)
		show_genre()

func _on_research_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "RESEARCH"
		clicked_section = 4
		animate_camera(section_4_cam)
		show_genre()

func _on_economics_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "ECONOMICS"
		clicked_section = 4
		animate_camera(section_4_cam)
		show_genre()

func _on_accounting_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "ACCOUNTING"
		clicked_section = 4
		animate_camera(section_4_cam)
		show_genre()

func _on_management_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "MANAGEMENT"
		clicked_section = 4
		animate_camera(section_4_cam)
		show_genre()

func _on_entrep_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "ENTREP"
		clicked_section = 4
		animate_camera(section_4_cam)
		show_genre()

func _on_mintgreen_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "MINTGREEN"
		clicked_section = 4
		animate_camera(section_4_cam)
		show_genre()

func _on_computer_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "COMPUTER"
		clicked_section = 5
		animate_camera(section_5_cam)
		show_genre()

func _on_electronics_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "ELECTRONICS"
		clicked_section = 5
		animate_camera(section_5_cam)
		show_genre()

func _on_entrep2_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "ENTREP"
		clicked_section = 5
		animate_camera(section_5_cam)
		show_genre()

func _on_computer2_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "COMPUTER"
		clicked_section = 5
		animate_camera(section_5_cam)
		show_genre()

func _on_computer3_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "COMPUTER"
		clicked_section = 6
		animate_camera(section_6_cam)
		show_genre()

func _on_shs2_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "SHS"
		clicked_section = 6
		animate_camera(section_6_cam)
		show_genre()

func _on_shs_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "SHS"
		clicked_section = 6
		animate_camera(section_6_cam)
		show_genre()

func _on_economics2_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "ECONOMICS"
		clicked_section = 6
		animate_camera(section_6_cam)
		show_genre()

func _on_nstp_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "NSTP"
		clicked_section = 6
		animate_camera(section_6_cam)
		show_genre()

func _on_pe_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "PE"
		clicked_section = 6
		animate_camera(section_6_cam)
		show_genre()

func _on_art_appreciation_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "ART APPRECIATION"
		clicked_section = 6
		animate_camera(section_6_cam)
		show_genre()


# HOVER MARKERS
func _on_philosophy_mouse_entered() -> void:
	philo_marker.visible = true
func _on_philosophy_mouse_exited() -> void:
	philo_marker.visible = false

func _on_psychology_mouse_entered() -> void:
	psych_marker.visible = true
func _on_psychology_mouse_exited() -> void:
	psych_marker.visible = false

func _on_education_mouse_entered() -> void:
	educ_marker.visible = true
func _on_education_mouse_exited() -> void:
	educ_marker.visible = false

func _on_science_mouse_entered() -> void:
	sci_marker.visible = true
func _on_science_mouse_exited() -> void:
	sci_marker.visible = false

func _on_english_mouse_entered() -> void:
	eng_marker.visible = true
func _on_english_mouse_exited() -> void:
	eng_marker.visible = false

func _on_law_mouse_entered() -> void:
	law_marker.visible = true
func _on_law_mouse_exited() -> void:
	law_marker.visible = false

func _on_miscellaneous_mouse_entered() -> void:
	mis_marker.visible = true
func _on_miscellaneous_mouse_exited() -> void:
	mis_marker.visible = false

func _on_miscellaneous2_mouse_entered() -> void:
	mis2_marker.visible = true
func _on_miscellaneous2_mouse_exited() -> void:
	mis2_marker.visible = false

func _on_filipino_mouse_entered() -> void:
	fil_marker.visible = true
func _on_filipino_mouse_exited() -> void:
	fil_marker.visible = false

func _on_genre_mouse_entered() -> void:
	genre_marker.visible = true
func _on_genre_mouse_exited() -> void:
	genre_marker.visible = false

func _on_math_mouse_entered() -> void:
	math_marker.visible = true
func _on_math_mouse_exited() -> void:
	math_marker.visible = false

func _on_literature_mouse_entered() -> void:
	lit_marker.visible = true
func _on_literature_mouse_exited() -> void:
	lit_marker.visible = false

func _on_humanities_mouse_entered() -> void:
	hum_marker.visible = true
func _on_humanities_mouse_exited() -> void:
	hum_marker.visible = false

func _on_research_mouse_entered() -> void:
	research_marker.visible = true
func _on_research_mouse_exited() -> void:
	research_marker.visible = false

func _on_economics_mouse_entered() -> void:
	econ_marker.visible = true
func _on_economics_mouse_exited() -> void:
	econ_marker.visible = false

func _on_accounting_mouse_entered() -> void:
	acc_marker.visible = true
func _on_accounting_mouse_exited() -> void:
	acc_marker.visible = false

func _on_management_mouse_entered() -> void:
	mgt_marker.visible = true
func _on_management_mouse_exited() -> void:
	mgt_marker.visible = false

func _on_entrep_mouse_entered() -> void:
	entrep_marker.visible = true
func _on_entrep_mouse_exited() -> void:
	entrep_marker.visible = false

func _on_mintgreen_mouse_entered() -> void:
	mint_marker.visible = true
func _on_mintgreen_mouse_exited() -> void:
	mint_marker.visible = false

func _on_computer_mouse_entered() -> void:
	comp_marker.visible = true
func _on_computer_mouse_exited() -> void:
	comp_marker.visible = false

func _on_electronics_mouse_entered() -> void:
	elec_marker.visible = true
func _on_electronics_mouse_exited() -> void:
	elec_marker.visible = false

func _on_entrep2_mouse_entered() -> void:
	entrep2_marker.visible = true
func _on_entrep2_mouse_exited() -> void:
	entrep2_marker.visible = false

func _on_computer2_mouse_entered() -> void:
	comp2_marker.visible = true
	comp2_marker_b.visible = true
func _on_computer2_mouse_exited() -> void:
	comp2_marker.visible = false
	comp2_marker_b.visible = false

func _on_computer3_mouse_entered() -> void:
	comp3_marker.visible = true
func _on_computer3_mouse_exited() -> void:
	comp3_marker.visible = false

func _on_shs2_mouse_entered() -> void:
	shs2_marker.visible = true
func _on_shs2_mouse_exited() -> void:
	shs2_marker.visible = false

func _on_shs_mouse_entered() -> void:
	shs_marker.visible = true
func _on_shs_mouse_exited() -> void:
	shs_marker.visible = false

func _on_economics2_mouse_entered() -> void:
	econ2_marker.visible = true
func _on_economics2_mouse_exited() -> void:
	econ2_marker.visible = false

func _on_nstp_mouse_entered() -> void:
	nstp_marker.visible = true
	nstp_marker_b.visible = true
func _on_nstp_mouse_exited() -> void:
	nstp_marker.visible = false
	nstp_marker_b.visible = false

func _on_pe_mouse_entered() -> void:
	pe_marker.visible = true
func _on_pe_mouse_exited() -> void:
	pe_marker.visible = false

func _on_art_appreciation_mouse_entered() -> void:
	art_marker.visible = true
func _on_art_appreciation_mouse_exited() -> void:
	art_marker.visible = false


# TRACKING BUTTON
func _on_button_pressed():
	if currently_clicked == "":
		path_to_section_1.visible = false
		hide_extra_paths()
		return
	animate_camera(top_down_camera)

	hide_extra_highlights()
	hide_extra_paths()

	if clicked_section >= 3:
		path_to_section_1.visible = false
		sec_1_highlight.visible = false
		sec_2_highlight.visible = false
		sec_3_highlight.visible = clicked_section == 3
		sec_4_highlight.visible = clicked_section == 4
		sec_5_highlight.visible = clicked_section == 5
		sec_5b_highlight.visible = clicked_section == 5
		sec_6_highlight.visible = clicked_section == 6
		path_to_section_3.visible = clicked_section == 3
		path_to_section_4.visible = clicked_section == 4
		path_to_section_5.visible = clicked_section == 5
		path_to_section_6.visible = clicked_section == 6
		return

	if currently_clicked == "MISC":
		
		path_to_section_1.visible = false
		path_to_section_2.visible = true
		sec_1_highlight.visible = false
		sec_2_highlight.visible = true
	else:
		path_to_section_1.visible = true
		sec_1_highlight.visible = true
		sec_2_highlight.visible = false
		

# =================================
# HIDE BUTTON
# =================================

func _on_button_2_pressed() -> void:

	hide.visible = false
	label.visible = false
	sprite_2d.visible = false

	show.visible = true


# =================================
# SHOW BUTTON
# =================================

func _on_hide_2_pressed() -> void:

	hide.visible = true
	label.visible = true
	sprite_2d.visible = true

	show.visible = false


var genre_books_list := {
	"PHILOSOPHY": philosophy_books,
	"PSYCHOLOGY": psychology_books,
	"EDUCATION": education_books,
	"SCIENCE": science_books,
	"ENGLISH": english_books,
	"LAW": law_books,
	"MISC": misc_books,
	"FILIPINO": filipino_books,
	"GENRE": genre_books,
	"MATH": math_books,
	"LITERATURE": literature_books,
	"HUMANITIES": humanities_books,
	"RESEARCH": research_books,
	"ECONOMICS": economics_books,
	"ACCOUNTING": accounting_books,
	"MANAGEMENT": management_books,
	"ENTREP": entrep_books,
	"MINTGREEN": mintgreen_books,
	"COMPUTER": computer_books,
	"ELECTRONICS": electronics_books,
	"SHS": shs_books,
	"NSTP": nstp_books,
	"PE": pe_books,
	"ART APPRECIATION": art_books
}

var genre_sections := {
	"PHILOSOPHY": 1, "PSYCHOLOGY": 1, "EDUCATION": 1, "SCIENCE": 1, "ENGLISH": 1, "LAW": 1,
	"MISC": 2,
	"FILIPINO": 3, "GENRE": 3, "MATH": 3, "LITERATURE": 3,
	"HUMANITIES": 4, "RESEARCH": 4, "ECONOMICS": 4, "ACCOUNTING": 4, "MANAGEMENT": 4, "ENTREP": 4, "MINTGREEN": 4,
	"COMPUTER": 5, "ELECTRONICS": 5,
	"SHS": 6, "NSTP": 6, "PE": 6, "ART APPRECIATION": 6
}


func go_to_genre(genre_name):
	var section = genre_sections[genre_name]
	currently_clicked = genre_name
	clicked_section = section

	if section == 1:
		animate_camera(section_1_cam)
	elif section == 2:
		animate_camera(section_2_cam)
	elif section == 3:
		animate_camera(section_3_cam)
	elif section == 4:
		animate_camera(section_4_cam)
	elif section == 5:
		animate_camera(section_5_cam)
	elif section == 6:
		animate_camera(section_6_cam)

	show_genre()


func _on_line_edit_text_submitted(new_text: String):
	search_queue = new_text.strip_edges().to_lower()

	if search_queue == "":
		return

	# 1. search by genre name
	for genre_name in genre_books_list:
		if genre_name.to_lower() == search_queue:
			go_to_genre(genre_name)
			return

	# 2. search by book title or author
	for genre_name in genre_books_list:
		for book in genre_books_list[genre_name]:
			if book.to_lower().contains(search_queue):
				go_to_genre(genre_name)
				return

	# 3. nothing found
	genre.text = "NOT FOUND"
	label.text = "No results for " + new_text
	genre_selected.visible = true
	genre_bg.visible = false
